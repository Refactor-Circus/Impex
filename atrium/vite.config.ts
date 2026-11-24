import type { Plugin, PluginOption } from 'vite'
import { atriumModule } from '@refactor-circus/atrium/vite'

// Impex's Atrium module: `pnpm build` writes ../dist/atrium (committed), `pnpm test` runs Vitest.
const config = atriumModule({
  key: 'impex',
  outDir: '../dist/atrium',
  inputs: ['src', 'package.json', 'vite.config.ts', 'pnpm-lock.yaml'],
  test: { setupFiles: ['test/setup.ts'] },
})

// The SDK's own components use <Atrium*> and <U*> without importing them, and
// Nuxt UI's component resolver skips files under node_modules, where the SDK
// sits once installed from GitHub (in Atrium's workspace it does not). Until
// the preset covers that, let the resolver transform the SDK's components in
// the test run as Atrium's own suite does.
const sdkComponent = /[\\/]node_modules[\\/]@refactor-circus[\\/]atrium[\\/]src[\\/].*\.vue(?:$|\?)/

async function resolveSdkComponents(plugins: PluginOption[]): Promise<PluginOption[]> {
  const resolved = (await Promise.all(plugins)).flat(Infinity as 1) as PluginOption[]

  for (const plugin of resolved) {
    const named = plugin as (Plugin & { transformInclude?: (id: string) => boolean }) | null

    if (named?.name === 'unplugin-vue-components' && named.transformInclude) {
      const include = named.transformInclude.bind(named)
      named.transformInclude = (id: string) => include(id) || sdkComponent.test(id)
    }
  }

  return resolved
}

export default process.env.VITEST
  ? { ...config, plugins: resolveSdkComponents(config.plugins as PluginOption[]) }
  : config
