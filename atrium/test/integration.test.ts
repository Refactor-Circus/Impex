import { describe, expect, it } from 'vitest'
import { expectIntegrationManifest } from '@refactor-circus/atrium/testing'
import Impex, { screens } from '../src/atrium'

/*
 * Impex declares its Atrium integration (nav, widgets, settings panel, search
 * source, screens, features and the run-link hook) in src/integration.json,
 * which the preset validates and copies to dist/atrium/integration.json. Each
 * key it names must be one the module registers, or Atrium would declare
 * something with nothing to render it.
 */

const manifest = expectIntegrationManifest('src/integration.json', 'impex')

describe('the Impex integration manifest', () => {
  it('is valid and declares the impex key at version 1', () => {
    expect(manifest.key).toBe('impex')
    expect(manifest.version).toBe(1)
  })

  it('maps every declared screen to a route in the module', () => {
    const keys = (manifest.screens ?? []).map(screen => screen.key)

    expect(keys).not.toHaveLength(0)
    expect(keys.filter(key => !(key in screens))).toEqual([])
    expect(Object.keys(screens).sort()).toEqual([...keys].sort())
  })

  it('registers a component for every declared widget', () => {
    const keys = (manifest.widgets ?? []).map(widget => String(widget.component ?? widget.key))

    expect(keys).not.toHaveLength(0)
    expect(keys.filter(key => !(key in (Impex.widgets ?? {})))).toEqual([])
  })

  it('registers an extension for every hook tab and section', () => {
    const keys = (manifest.hooks ?? []).flatMap(hook => [
      ...Object.keys((hook.tabs ?? {}) as Record<string, unknown>),
      ...Object.keys((hook.sections ?? {}) as Record<string, unknown>)
    ])

    expect(keys).toEqual(['impex.run-link'])
    expect(keys.filter(key => !(key in (Impex.extensions ?? {})))).toEqual([])
  })

  it('registers the settings panel, search source and live resolver it declares', () => {
    const settings = manifest.settings as { key: string, component?: string } | null | undefined
    const searchKeys = (manifest.search_sources ?? []).map(source => String(source.key))
    const liveKeys = (manifest.navigation ?? []).flatMap((item) => {
      const live = item.live as { key: string } | undefined

      return live ? [live.key] : []
    })

    expect(settings?.component ?? settings?.key).toBeDefined()
    expect(Impex.settingsPanels ?? {}).toHaveProperty([String(settings?.component ?? settings?.key)])
    expect(searchKeys.filter(key => !(key in (Impex.searchSources ?? {})))).toEqual([])
    expect(liveKeys).toEqual(['impex.active-runs'])
    expect(liveKeys.filter(key => !(key in (Impex.liveResolvers ?? {})))).toEqual([])
  })
})
