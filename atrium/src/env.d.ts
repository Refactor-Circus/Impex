/// <reference types="vite/client" />
import type { paths } from '../../client/src/schema'

// Types `useAtriumIntegration('impex')`, `useIntegrationQuery('impex', …)` and
// `mockPackageApi('impex', …)` by Impex's API.
declare module '@refactor-circus/atrium' {
  interface AtriumIntegrationPaths {
    impex: paths
  }
}
