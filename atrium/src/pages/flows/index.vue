<script setup lang="ts">
import ImpexFlash from '../../components/ImpexFlash.vue'
import ImpexState from '../../components/ImpexState.vue'
import { looseBody, parseJson, runPath, useImpexAction, useImpexResource } from '../../components/shared'
import type { components } from '../../../../client/src/schema'
import type { TableColumn } from '@nuxt/ui'

/**
 * `impex.flows`: every registered flow (`impex.flows.index`), each with v1's
 * "run" form, which starts a run with optional JSON arguments
 * (`impex.flows.runs.store`) and opens it.
 */
const { t } = useI18n()
const router = useRouter()

const flows = useImpexResource(async client => integrationData(await client.GET('/flows')).data)
const action = useImpexAction()
const args = reactive<Record<string, string>>({})

const columns = computed<TableColumn<NonNullable<typeof flows.data.value>[number]>[]>(() => [
  { accessorKey: 'slug', header: t('impex.flows.slug') },
  { accessorKey: 'schedule', header: t('impex.flows.schedule'), meta: { class: { td: 'font-mono text-xs' } } },
  { accessorKey: 'enabled', header: t('impex.flows.state') },
  { id: 'run', header: t('impex.flows.run') }
])

async function start(flow: string): Promise<void> {
  const client = flows.client
  const parsed = parseJson(args[flow] ?? '')

  if (!client) {
    return
  }

  const value = parsed && 'value' in parsed ? parsed.value : undefined

  // As in v1, arguments are a JSON array or object.
  if (parsed && (typeof value !== 'object' || value === null)) {
    action.invalid('arguments', t('impex.flows.arguments_invalid'))
    return
  }

  const body = looseBody<components['schemas']['StoreFlowRunRequest']>(value === undefined ? {} : { arguments: value })

  await action.run(
    async () => integrationData(await client.POST('/flows/{flow}/runs', { params: { path: { flow } }, body })),
    async (answer) => {
      args[flow] = ''
      await router.push(runPath(answer.data.id))
    }
  )
}
</script>

<template>
  <div class="space-y-4" data-testid="impex-flows">
    <AtriumPageHeader :title="t('impex.flows.title')" />

    <AtriumIntegrationScreen screen-key="impex.flows">
      <div class="space-y-4">
        <ImpexFlash :action="action" />

        <ImpexState :error="flows.error.value" :ready="flows.data.value !== null">
          <UTable :data="flows.data.value ?? []" :columns="columns" data-testid="impex-flows-table">
            <template #slug-cell="{ row }">
              <div :data-testid="`impex-flow-row-${row.original.slug}`">
                <p class="font-medium">{{ row.original.slug }}</p>
                <p class="font-mono text-xs text-muted">{{ row.original.class }}</p>
              </div>
            </template>
            <template #schedule-cell="{ row }">
              {{ row.original.schedule ?? t('impex.common.none') }}
            </template>
            <template #enabled-cell="{ row }">
              <UBadge :color="row.original.enabled ? 'success' : 'neutral'" variant="subtle">
                {{ row.original.enabled ? t('impex.flows.enabled') : t('impex.flows.disabled') }}
              </UBadge>
            </template>
            <template #run-cell="{ row }">
              <UForm :state="args" class="flex items-center gap-2" :data-testid="`impex-flow-run-${row.original.slug}`" @submit="start(row.original.slug)">
                <UInput v-model="args[row.original.slug]" name="arguments" :placeholder="t('impex.flows.arguments')" size="sm" class="w-56" />
                <UButton type="submit" size="sm" icon="i-lucide-play" :loading="action.busy.value">{{ t('impex.flows.run') }}</UButton>
              </UForm>
            </template>
            <template #empty>
              <AtriumState compact :title="t('impex.flows.empty')" data-testid="impex-flows-empty" />
            </template>
          </UTable>
        </ImpexState>
      </div>
    </AtriumIntegrationScreen>
  </div>
</template>
