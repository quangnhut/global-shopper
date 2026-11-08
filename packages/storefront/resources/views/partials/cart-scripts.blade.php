{{-- Add to cart without leaving the page, the variant picker and the toast. Alpine comes with Livewire. --}}
<div
    x-data="sfCartUi"
    x-on:sf-toast.window="toast($event.detail)"
    x-on:sf-choose-variant.window="choose($event.detail)"
>
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4" aria-live="polite" role="status">
        <template x-if="message">
            <div
                class="pointer-events-auto flex items-center gap-3 rounded-xl px-4 py-3 text-sm shadow-lg ring-1"
                x-bind:class="error ? 'bg-red-50 text-red-800 ring-red-200' : 'bg-gray-900 text-white ring-black/10'"
            >
                <span x-text="message"></span>
                <a x-show="! error && cartUrl" x-bind:href="cartUrl" class="font-semibold text-amber-300 underline">{{ __('shopper-storefront::cart.view_cart') }}</a>
            </div>
        </template>
    </div>

    <dialog x-ref="dialog" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl p-0 shadow-xl backdrop:bg-black/40" aria-labelledby="sf-variant-title" x-on:close="variants = []">
        <div class="p-4">
            <div class="flex items-start justify-between gap-4">
                <h2 id="sf-variant-title" class="font-semibold text-gray-900" x-text="title"></h2>
                <button type="button" class="rounded-lg p-1 text-gray-500 hover:bg-gray-100" x-on:click="$refs.dialog.close()" aria-label="{{ __('shopper-storefront::cart.close') }}">
                    <x-shopper-storefront::icon name="close" class="size-5" />
                </button>
            </div>
            <ul class="mt-3 divide-y divide-gray-100">
                <template x-for="variant in variants" x-bind:key="variant.id">
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-gray-800" x-text="variant.name"></span>
                            <span class="text-sm text-brand-600" x-text="variant.price"></span>
                        </span>
                        <button
                            type="button"
                            class="sf-button shrink-0 px-3 py-1.5 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500"
                            x-bind:disabled="! variant.in_stock || busy"
                            x-on:click="addVariant(variant.id)"
                            x-text="variant.in_stock ? @js(__('shopper-storefront::cart.choose')) : @js(__('shopper-storefront::cart.out_of_stock'))"
                        ></button>
                    </li>
                </template>
            </ul>
        </div>
    </dialog>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        const genericError = @js(__('shopper-storefront::cart.errors.generic'))

        async function postToCart(url, body) {
            const response = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body,
                credentials: 'same-origin',
            })
            let data = {}
            try {
                data = await response.json()
            } catch (error) {}

            return { status: response.status, ok: response.ok, data }
        }

        function announce(result) {
            if (result.ok) {
                window.Livewire?.dispatch('cart-updated')
            }
            window.dispatchEvent(new CustomEvent('sf-toast', {
                detail: { message: result.data.message || genericError, error: ! result.ok, cartUrl: result.data.cart_url },
            }))
        }

        Alpine.data('sfAddToCart', () => ({
            busy: false,
            async submit() {
                this.busy = true
                const form = this.$el
                try {
                    const result = await postToCart(form.action, new FormData(form))
                    if (result.status === 409 && result.data.variants) {
                        window.dispatchEvent(new CustomEvent('sf-choose-variant', {
                            detail: { action: form.action, form: new FormData(form), title: result.data.message, variants: result.data.variants },
                        }))
                    } else {
                        announce(result)
                    }
                } catch (error) {
                    announce({ ok: false, data: {} })
                } finally {
                    this.busy = false
                }
            },
        }))

        Alpine.data('sfCartUi', () => ({
            message: null,
            error: false,
            cartUrl: null,
            timer: null,
            title: '',
            variants: [],
            action: null,
            form: null,
            busy: false,
            toast(detail) {
                clearTimeout(this.timer)
                this.message = detail.message
                this.error = detail.error
                this.cartUrl = detail.cartUrl || null
                this.timer = setTimeout(() => (this.message = null), 4000)
            },
            choose(detail) {
                this.title = detail.title
                this.variants = detail.variants
                this.action = detail.action
                this.form = detail.form
                this.$refs.dialog.showModal()
            },
            async addVariant(id) {
                this.busy = true
                const body = this.form
                body.set('variant_id', id)
                try {
                    announce(await postToCart(this.action, body))
                } catch (error) {
                    announce({ ok: false, data: {} })
                } finally {
                    this.busy = false
                    this.$refs.dialog.close()
                }
            },
        }))
    })
</script>
