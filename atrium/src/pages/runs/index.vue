<script setup lang="ts">
import ImpexState from '../../components/ImpexState.vue'
import ImpexStatusBadge from '../../components/ImpexStatusBadge.vue'
import { formatDate, queryValue, RUN_STATUSES, RUN_TRIGGERS, runPath, useImpexList } from '../../components/shared'
import type { components } from '../../../../client/src/schema'
import type { TableColumn } from '@nuxt/ui'

/**
 * `impex.runs`: every run the user may see, newest first, filtered by
 * status, trigger and flow as v1 was (`impex.runs.index`), a cursor page at
 * a time. The filters live in the URL.
 */
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const filters = computed(() => ({
  status: queryValue(route.query.status) as components['schemas']['RunStatus'] | undefined,
  trigger: queryValue(route.query.trigger) as components['schemas']['RunTrigger'] | undefined,
  flow: queryValue(route.query.flow)
}))

const form = reactive({ ...filters.value })

watch(filters, (value) => {
  Object.assign(form, value)
})

const runs = useImpexList(
  async (client, cursor) => integrationData(await client.GET('/runs', { params: { query: { ...filters.value, cursor } } })),
  filters
)

const columns = computed<TableColumn<(typeof runs.rows.value)[number]>[]>(() => [
  { accessorKey: 'flow', header: t('impex.runs.flow') },
  { accessorKey: 'status', header: t('impex.runs.status') },
  { accessorKey: 'trigger', header: t('impex.runs.trigger') },
  { accessorKey: 'started_at', header: t('impex.runs.started') },
  { accessorKey: 'finished_at', header: t('impex.runs.finished') }
])

const statusItems = computed(() => RUN_STATUSES.map(status => ({ label: t(`impex.statuses.${status}`), value: status })))
const triggerItems = computed(() => RUN_TRIGGERS.map(trigger => ({ label: trigger, value: trigger })))

function filter(): void {
  router.replace({ query: { ...route.query, status: form.status || undefined, trigger: form.trigger || undefined, flow: form.flow || undefined } })
}

function clear(): void {
  router.replace({ query: { ...route.query, status: undefined, trigger: undefined, flow: undefined } })
}
</script>

<template>
  <div class="space-y-4" data-testid="impex-runs">
    <AtriumPageHeader :title="t('impex.runs.title')" />

    <AtriumIntegrationScreen screen-key="impex.runs">
      <div class="space-y-4">
        <UForm :state="form" class="flex flex-wrap items-center gap-2" data-testid="impex-runs-filters" @submit="filter">
          <USelect v-model="form.status" :items="statusItems" :placeholder="t('impex.runs.all_statuses')" class="w-44" :aria-label="t('impex.runs.status')" />
          <USelect v-model="form.trigger" :items="triggerItems" :placeholder="t('impex.runs.all_triggers')" class="w-40" :aria-label="t('impex.runs.trigger')" />
          <UInput v-model="form.flow" name="flow" :placeholder="t('impex.runs.flow')" class="w-48" />
          <UButton type="submit" icon="i-lucide-filter" data-testid="impex-runs-filter">{{ t('impex.common.filter') }}</UButton>
          <UButton variant="ghost" data-testid="impex-runs-clear" @click="clear">{{ t('impex.common.clear') }}</UButton>
        </UForm>

        <ImpexState :error="runs.error.value" :ready="!runs.pending.value || runs.rows.value.length > 0">
          <UTable :data="runs.rows.value" :columns="columns" :loading="runs.pending.value" data-testid="impex-runs-table">
            <template #flow-cell="{ row }">
              <ULink :to="runPath(row.original.id)" class="font-medium" :data-testid="`impex-run-row-${row.original.id}`">{{ row.original.flow }}</ULink>
            </template>
            <template #status-cell="{ row }">
              <ImpexStatusBadge :status="row.original.status" />
            </template>
            <template #started_at-cell="{ row }">
              {{ formatDate(row.original.started_at) ?? t('impex.common.none') }}
            </template>
            <template #finished_at-cell="{ row }">
              {{ formatDate(row.original.finished_at) ?? t('impex.common.none') }}
            </template>
            <template #empty>
              <AtriumState compact :title="t('impex.runs.empty')" data-testid="impex-runs-empty" />
            </template>
          </UTable>

          <UButton v-if="runs.nextCursor.value" variant="outline" :loading="runs.pending.value" data-testid="impex-load-more" @click="runs.loadMore">
            {{ t('impex.common.load_more') }}
          </UButton>
        </ImpexState>
      </div>
    </AtriumIntegrationScreen>
  </div>
</template>
