import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import RunsPage from '../src/pages/runs/index.vue'
import { chooseOption, selectLabels, selectValue, selectValues, settle, useRouter } from '@refactor-circus/atrium/testing'
import { mockPackageApi, reply } from '@refactor-circus/atrium/testing'
import { tableRow } from '@refactor-circus/atrium/testing'
import { atriumApi, callsTo, failedRun, leaveImpex, mountImpexPage, page, resetImpex, run } from './support'
import { mockAtriumApi } from '@refactor-circus/atrium/testing'

/*
 * `impex.runs` (AC 54, 56): the runs list from `impex.runs.index`, filtered
 * by status, trigger and flow from the URL, a cursor page at a time.
 */

const { api } = vi.hoisted(() => ({ api: vi.fn() }))

mockAtriumApi(api)

describe('impex.runs', () => {
  beforeEach(async () => {
    api.mockReset()
    api.mockImplementation(atriumApi)
    await resetImpex()
  })

  afterEach(leaveImpex)

  it('lists the runs from impex.runs.index, each linking to its run', async () => {
    const calls = mockPackageApi('impex', { 'GET /runs': page([run, failedRun]) })

    const wrapper = await mountImpexPage(RunsPage, '/impex/runs')

    expect(callsTo(calls, 'GET', '/runs').length).toBeGreaterThan(0)
    expect(tableRow(wrapper, 'impex-run-row-run-1').text()).toContain('orders.sync')
    expect(tableRow(wrapper, 'impex-run-row-run-2').find('a').attributes('href')).toBe('/impex/runs/run-2')
  })

  it('asks atrium.screens.show for impex.runs, with no record', async () => {
    mockPackageApi('impex', { 'GET /runs': page([run]) })

    await mountImpexPage(RunsPage, '/impex/runs')

    expect(api).toHaveBeenCalledWith('/screens/impex.runs', { query: { record: undefined, tab: undefined } })
  })

  it('sends the status, trigger and flow filters from the URL as the endpoint\'s query', async () => {
    const calls = mockPackageApi('impex', { 'GET /runs': page([failedRun]) })

    await mountImpexPage(RunsPage, '/impex/runs?status=failed&trigger=api&flow=orders.sync')

    expect(callsTo(calls, 'GET', '/runs').map(call => call.query)).toContainEqual({ status: 'failed', trigger: 'api', flow: 'orders.sync' })
  })

  it('offers "Any" first in the status and trigger filters, which sends no filter', async () => {
    const calls = mockPackageApi('impex', { 'GET /runs': page([run]) })

    const wrapper = await mountImpexPage(RunsPage, '/impex/runs?status=failed')

    expect(selectValues(wrapper, 'impex-runs-status')[0]).toBe('')
    expect(selectLabels(wrapper, 'impex-runs-status')[0]).toBe('Any status')
    expect(selectValues(wrapper, 'impex-runs-trigger')[0]).toBe('')
    expect(selectValue(wrapper, 'impex-runs-status')).toBe('failed')

    await chooseOption(wrapper, 'impex-runs-status', '')
    await chooseOption(wrapper, 'impex-runs-trigger', 'api')
    await wrapper.find('[data-testid="impex-runs-filters"]').trigger('submit')
    await settle()

    expect(useRouter().currentRoute.value.query).toEqual({ trigger: 'api' })
    expect(callsTo(calls, 'GET', '/runs').at(-1)?.query).toEqual({ trigger: 'api' })
  })

  it('loads the next page by the cursor the last one gave', async () => {
    const calls = mockPackageApi('impex', {
      'GET /runs': call => call.query.cursor === 'next-1' ? page([failedRun]) : page([run], 'next-1')
    })

    const wrapper = await mountImpexPage(RunsPage, '/impex/runs')
    await wrapper.find('[data-testid="impex-load-more"]').trigger('click')
    await settle()

    expect(callsTo(calls, 'GET', '/runs').some(call => call.query.cursor === 'next-1')).toBe(true)
    expect(tableRow(wrapper, 'impex-run-row-run-1').exists()).toBe(true)
    expect(tableRow(wrapper, 'impex-run-row-run-2').exists()).toBe(true)
  })

  it('shows the empty state', async () => {
    mockPackageApi('impex', { 'GET /runs': page([]) })

    const wrapper = await mountImpexPage(RunsPage, '/impex/runs')

    expect(wrapper.find('[data-testid="impex-runs-empty"]').exists()).toBe(true)
  })

  it('shows the no-access state on a 403', async () => {
    mockPackageApi('impex', { 'GET /runs': reply(403) })

    const wrapper = await mountImpexPage(RunsPage, '/impex/runs')

    expect(wrapper.find('[data-testid="atrium-no-access"]').exists()).toBe(true)
  })
})
