import { createHighlighterCore } from 'shiki/core'
import { createJavaScriptRegexEngine } from 'shiki/engine/javascript'
import json from 'shiki/langs/json.mjs'
import githubDarkDefault from 'shiki/themes/github-dark-default.mjs'
import githubLight from 'shiki/themes/github-light.mjs'

// Import only what the views use: the plain `shiki` entry bundles every grammar and theme (about 9 MB).
// A view that needs another language or theme must add it here.
let highlighter = null

const getHighlighter = () => {
  highlighter ??= createHighlighterCore({
    themes: [githubLight, githubDarkDefault],
    langs: [json],
    engine: createJavaScriptRegexEngine(),
  })

  return highlighter
}

const CodePreview = ({ code, lang, themes }) => {
  return {
    code,
    lang,
    themes,
    previewCode: null,

    async init() {
      const shiki = await getHighlighter()

      this.previewCode = shiki.codeToHtml(this.code, {
        lang,
        themes: this.themes,
      })

      this.$el.innerHTML = this.previewCode
    },

    destroy() {
      this.previewCode = null
    },
  }
}

export default CodePreview
