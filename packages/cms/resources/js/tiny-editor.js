// Alpine component for the `TinyEditor` Filament field: keeps a TinyMCE instance and the
// Livewire state in sync. Loaded on demand by Filament (`x-load-src`).

let tinymceLoading = null

function loadTinymce(baseUrl) {
  if (window.tinymce) {
    return Promise.resolve(window.tinymce)
  }

  tinymceLoading ??= new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = `${baseUrl}/tinymce.min.js`
    script.referrerPolicy = 'origin'
    script.onload = () => resolve(window.tinymce)
    script.onerror = () => {
      tinymceLoading = null
      reject(new Error(`TinyMCE could not be loaded from ${script.src}`))
    }
    document.head.appendChild(script)
  })

  return tinymceLoading
}

function isDarkMode() {
  return document.documentElement.classList.contains('dark')
}

export default function tinyEditor({ state, config, baseUrl, uploadUrl, mediaAction, mediaLabel, statePath, disabled }) {
  // Kept outside Alpine's reactive data on purpose: Alpine would wrap the editor in a Proxy,
  // and TinyMCE's registry (tinymce.remove / tinymce.get) matches editors by identity.
  let editor = null
  let onNavigating = null

  return {
    state,

    async init() {
      const tinymce = await loadTinymce(baseUrl)

      // The element can be gone already when the user navigates away while TinyMCE loads.
      if (! this.$refs.textarea?.isConnected) {
        return
      }

      // Field ids repeat between pages (e.g. `form.content`): drop an instance left over from a
      // previous page so TinyMCE does not refuse to start or keep two editors with one id.
      const stale = tinymce.get(this.$refs.textarea.id)

      if (stale && stale.getElement() !== this.$refs.textarea) {
        tinymce.remove(stale)
      }

      const dark = isDarkMode()

      await tinymce.init({
        ...config,
        target: this.$refs.textarea,
        base_url: baseUrl,
        suffix: '.min',
        skin: dark ? 'oxide-dark' : 'oxide',
        content_css: dark ? 'dark' : 'default',
        readonly: disabled,
        images_upload_handler: uploadUrl ? (blob, progress) => this.upload(blob, progress) : undefined,
        setup: (instance) => {
          editor = instance

          if (mediaAction) {
            // WordPress's "Add Media": opens a Filament action on the Livewire page, which answers
            // with a `cms-editor-insert` event handled by insertHtml().
            instance.ui.registry.addButton('addmedia', {
              icon: 'gallery',
              text: mediaLabel,
              onAction: () => this.$wire.mountAction(mediaAction, { statePath }),
            })
          }

          instance.on('init', () => instance.setContent(this.state ?? ''))

          // Only user edits mark the form dirty; programmatic setContent() does not.
          instance.on('input change undo redo', () => this.pushState())
        },
      })

      // `wire:navigate` swaps the page without always running Alpine's destroy(), so the
      // editor is also removed when Livewire announces a navigation.
      onNavigating = () => this.destroy()
      document.addEventListener('livewire:navigating', onNavigating, { once: true })

      this.$watch('state', (value) => {
        if (editor && (value ?? '') !== editor.getContent()) {
          editor.setContent(value ?? '')
        }
      })
    },

    insertHtml({ statePath: target, html }) {
      if (! editor || target !== statePath) {
        return
      }

      editor.insertContent(html)
      this.pushState()
    },

    pushState() {
      const content = editor.getContent()

      if (content === (this.state ?? '')) {
        return
      }

      this.state = content
      this.$el.dispatchEvent(new Event('input', { bubbles: true }))
    },

    upload(blob, progress) {
      return new Promise((resolve, reject) => {
        const data = new FormData()
        data.append('file', blob.blob(), blob.filename())

        const request = new XMLHttpRequest()
        request.open('POST', uploadUrl)
        request.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content ?? '')
        request.setRequestHeader('Accept', 'application/json')
        request.upload.onprogress = (event) => progress((event.loaded / event.total) * 100)
        request.onload = () => {
          const response = JSON.parse(request.responseText || '{}')

          if (request.status < 200 || request.status >= 300 || ! response.location) {
            reject({ message: response.message ?? `Upload failed (HTTP ${request.status})`, remove: true })

            return
          }

          resolve(response.location)
        }
        request.onerror = () => reject({ message: 'Upload failed: network error', remove: true })
        request.send(data)
      })
    },

    // Alpine calls this when the element is removed (including `wire:navigate` page changes),
    // otherwise TinyMCE instances leak and the next editor fails to start.
    destroy() {
      document.removeEventListener('livewire:navigating', onNavigating)

      if (editor) {
        window.tinymce?.remove(editor)
      }

      editor = null
    },
  }
}
