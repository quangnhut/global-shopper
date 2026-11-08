@php
    /**
     * Google website translator, driven by the header language picker (`sfTranslate` Alpine store).
     * element.js is injected only when a customer picks another language, or comes back with Google's `googtrans`
     * cookie set to one, so nothing is sent to Google before that. Back to the page language = drop the cookie + reload
     * (Google's own "show original" is unreliable from a hidden widget).
     *
     * @var array<string, array{label: string, short: string, flag: string}> $languages
     */
    $translateConfig = [
        'pageLanguage' => (string) config('shopper.storefront.translate.page_language', 'vi'),
        'languages' => array_keys($languages),
        'script' => (string) config('shopper.storefront.translate.script'),
        'failed' => __('shopper-storefront::storefront.header.translate_failed'),
    ];
@endphp

{{-- Google renders its own (hidden) language select in here. --}}
<div id="google_translate_element" class="sf-translate-widget" translate="no" aria-hidden="true"></div>

<script>
    document.addEventListener('alpine:init', () => {
        const config = @js($translateConfig);
        const COOKIE = 'googtrans'
        const TIMEOUT = 10000

        const fromCookie = () => {
            const match = document.cookie.match(/(?:^|;\s*)googtrans=([^;]*)/)
            const code = match ? decodeURIComponent(match[1]).split('/')[2] : null

            return code && config.languages.includes(code) ? code : config.pageLanguage
        }

        // Google may have written the cookie on the host or on a parent domain: drop every variant.
        const clearCookie = () => {
            const expired = `${COOKIE}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`
            const parts = location.hostname.split('.')

            document.cookie = expired
            for (let index = 0; index < parts.length - 1; index++) {
                const domain = parts.slice(index).join('.')
                document.cookie = `${expired}; domain=${domain}`
                document.cookie = `${expired}; domain=.${domain}`
            }
        }

        const setCookie = (code) => {
            document.cookie = `${COOKIE}=/${config.pageLanguage}/${code}; path=/; max-age=31536000; SameSite=Lax`
        }

        let loading = null

        // Resolves with Google's <select class="goog-te-combo"> once the widget is on the page.
        const load = () => {
            loading ??= new Promise((resolve, reject) => {
                window.googleTranslateElementInit = () => {
                    try {
                        new window.google.translate.TranslateElement(
                            {
                                pageLanguage: config.pageLanguage,
                                includedLanguages: config.languages.filter((code) => code !== config.pageLanguage).join(','),
                                autoDisplay: false,
                            },
                            'google_translate_element',
                        )
                    } catch (error) {
                        reject(error)
                    }
                }

                const started = Date.now()
                const poll = setInterval(() => {
                    const combo = document.querySelector('#google_translate_element select.goog-te-combo')

                    if (combo && combo.options.length > 1) {
                        clearInterval(poll)
                        resolve(combo)
                    } else if (Date.now() - started > TIMEOUT) {
                        clearInterval(poll)
                        reject(new Error('Google Translate did not start'))
                    }
                }, 100)

                const script = document.createElement('script')
                script.src = config.script
                script.async = true
                script.onerror = () => {
                    clearInterval(poll)
                    reject(new Error('Google Translate could not be loaded'))
                }
                document.head.appendChild(script)
            })

            return loading
        }

        Alpine.store('sfTranslate', {
            current: fromCookie(),
            loading: false,
            failed: false,

            init() {
                // Google translates by itself from the cookie once its script runs.
                if (this.current !== config.pageLanguage) {
                    this.loading = true
                    load()
                        .then(() => (this.loading = false))
                        .catch(() => this.fail())
                }
            },

            async choose(code) {
                if (! config.languages.includes(code) || (code === this.current && ! this.failed)) {
                    return
                }

                if (code === config.pageLanguage) {
                    clearCookie()
                    location.reload()

                    return
                }

                this.loading = true
                this.failed = false

                try {
                    const combo = await load()

                    setCookie(code)
                    combo.value = code
                    combo.dispatchEvent(new Event('change'))
                    this.current = code
                    this.loading = false
                } catch (error) {
                    this.fail()
                }
            },

            fail() {
                clearCookie()
                this.current = config.pageLanguage
                this.loading = false
                this.failed = true
                loading = null
                window.dispatchEvent(new CustomEvent('sf-toast', { detail: { message: config.failed, error: true } }))
            },
        })
    })
</script>
