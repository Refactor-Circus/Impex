import createClient, { type Client, type ClientOptions } from 'openapi-fetch';
import type { paths } from './schema';

export type { components, operations, paths } from './schema';

/**
 * openapi-fetch's client options, with a required `baseUrl`: the host's API
 * base URL, its `impex.routes.prefix` included (e.g. `https://app.test/impex`).
 * The document's paths are relative to it.
 */
export type ImpexClientOptions = ClientOptions & {
  baseUrl: string;
};

export type ImpexClient = Client<paths>;

/**
 * A typed openapi-fetch client for the Impex JSON API:
 * `client.GET('/search', { params: { query: { q: 'import' } } })`.
 */
export function createImpexClient(options: ImpexClientOptions): ImpexClient {
  return createClient<paths>(options);
}
