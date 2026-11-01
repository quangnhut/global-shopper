import * as esbuild from 'esbuild'
import { cp, rm } from 'node:fs/promises'
import path from 'node:path'

const isDev = process.argv.includes('--dev')
const targetPackage = process.argv.find(arg => arg.startsWith('--package='))?.split('=')[1]

async function compile(options) {
  const context = await esbuild.context(options)

  if (isDev) {
    await context.watch()
  } else {
    await context.rebuild()
    await context.dispose()
  }
}

const defaultOptions = {
  define: {
    'process.env.NODE_ENV': isDev ? `'development'` : `'production'`,
  },
  bundle: true,
  mainFields: ['module', 'main'],
  platform: 'neutral',
  sourcemap: isDev ? 'inline' : false,
  sourcesContent: isDev,
  treeShaking: true,
  target: ['es2020'],
  minify: !isDev,
  plugins: [{
    name: 'watchPlugin',
    setup: function (build) {
      build.onStart(() => {
        console.log(`Build started at ${new Date(Date.now()).toLocaleTimeString()}: ${build.initialOptions.outfile}`)
      })

      build.onEnd(result => {
        if (result.errors.length > 0) {
          console.log(`Build failed at ${new Date(Date.now()).toLocaleTimeString()}: ${build.initialOptions.outfile}`, result.errors)
        } else {
          console.log(`Build finished at ${new Date(Date.now()).toLocaleTimeString()}: ${build.initialOptions.outfile}`)
        }
      })
    }
  }],
}

const packages = [
  { name: 'admin', entry: 'shopper.js', outDir: 'dist', outFile: 'shopper.js' },
  { name: 'sidebar', entry: 'index.js', outDir: 'dist', outFile: 'sidebar.js' },
  // Filament loads it on demand (x-load-src) and expects an ES module with a default export.
  { name: 'cms', entry: 'tiny-editor.js', outDir: 'dist', outFile: 'tiny-editor.js', format: 'esm' },
]

// TinyMCE loads its plugins, skins and icons by URL at runtime, so it is copied (not bundled)
// into packages/cms/dist/tinymce and published to public/ by `vendor:publish --tag=shopper-cms-assets`.
// Only the minified runtime files are kept.
async function copyTinymce() {
  const source = './node_modules/tinymce'
  const target = './packages/cms/dist/tinymce'

  await rm(target, { recursive: true, force: true })
  await cp(source, target, {
    recursive: true,
    filter: (file) => {
      const relative = path.relative(source, file)

      if (relative === '' || ['models', 'plugins', 'skins', 'themes', 'icons'].some((dir) => relative === dir || relative.startsWith(dir + path.sep))) {
        return !path.extname(relative) || /\.min\.(js|css)$/.test(relative)
      }

      return ['tinymce.min.js', 'license.md', 'notices.txt'].includes(relative)
    },
  })

  console.log(`TinyMCE copied to ${target}`)
}

const filteredPackages = targetPackage
  ? packages.filter(pkg => pkg.name === targetPackage)
  : packages

if (filteredPackages.some((pkg) => pkg.name === 'cms')) {
  await copyTinymce()
}

filteredPackages.forEach(pkg => {
  compile({
    ...defaultOptions,
    platform: 'browser',
    ...(pkg.format ? { format: pkg.format } : {}),
    entryPoints: [`./packages/${pkg.name}/resources/js/${pkg.entry}`],
    outfile: `./packages/${pkg.name}/${pkg.outDir}/${pkg.outFile}`,
  })
})
