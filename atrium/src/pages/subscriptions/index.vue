<script setup lang="ts">
import ImpexState from '../../components/ImpexState.vue'
import ImpexStatusBadge from '../../components/ImpexStatusBadge.vue'
import { endpointOf, formatDate, NUMERIC_COLUMN, queryValue, SUBSCRIPTION_STATUSES, subscriptionPath, useImpexList } from '../../components/shared'
import type { components } from '../../../../client/src/schema'
import type { TableColumn } from '@nuxt/ui'

/**
 * `impex.subscriptions`: every subscription the user may see, filtered by
 * stream and status as v1 was (`impex.subscriptions.index`), a cursor page at
 * a time. The filters live in the URL.
 */
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const filters = computed(() => ({
  stream: queryValue(route.query.stream),
  status: queryValue(route.query.status) as components['schemas']['SubscriptionStatus'] | undefined
}))

const form = reactive({ ...filters.value })

watch(filters, (value) => {
  Object.assign(form, value)
})

const subscriptions = useImpexList(
  async (client, cursor) => integrationData(await client.GET('/subscriptions', { params: { query: { ...filters.value, cursor } } })),
  filters
)

const columns = computed<TableColumn<(typeof subscriptions.rows.value)[number]>[]>(() => [
  { accessorKey: 'stream', header: t('impex.subscriptions.stream') },
  { accessorKey: 'subscriber_id', header: t('impex.subscriptions.subscriber'), meta: { class: { td: 'font-mono text-xs' } } },
  { accessorKey: 'endpoint', header: t('impex.subscriptions.endpoint'), meta: { class: { td: 'max-w-xs truncate' } } },
  { accessorKey: 'status', header: t('impex.subscriptions.status') },
  { accessorKey: 'failures', header: t('impex.subscriptions.failures'), meta: NUMERIC_COLUMN },
  { accessorKey: 'last_delivered_at', header: t('impex.subscriptions.last_delivered') }
])

const statusItems = computed(() => SUBSCRIPTION_STATUSES.map(status => ({ label: t(`impex.statuses.${status}`), value: status })))

function filter(): void {
  router.replace({ query: { ...route.query, stream: form.stream || undefined, status: form.status || undefined } })
}

function clear(): void {
  router.replace({ query: { ...route.query, stream: undefined, status: undefined } })
}
</script>

<template>
  <div class="space-y-4" data-testid="impex-subscriptions">
    <AtriumPageHeader :title="t('impex.subscriptions.title')" />

    <AtriumIntegrationScreen screen-key="impex.subscriptions">
      <div class="space-y-4">
        <UForm :state="form" class="flex flex-wrap items-center gap-2" data-testid="impex-subscriptions-filters" @submit="filter">
          <UInput v-model="form.stream" name="stream" :placeholder="t('impex.subscriptions.stream')" class="w-48" />
          <USelect v-model="form.status" :items="statusItems" :placeholder="t('impex.subscriptions.all_statuses')" class="w-44" :aria-label="t('impex.subscriptions.status')" />
          <UButton type="submit" icon="i-lucide-filter" data-testid="impex-subscriptions-filter">{{ t('impex.common.filter') }}</UButton>
          <UButton variant="ghost" data-testid="impex-subscriptions-clear" @click="clear">{{ t('impex.common.clear') }}</UButton>
        </UForm>

        <ImpexState :error="subscriptions.error.value" :ready="!subscriptions.pending.value || subscriptions.rows.value.length > 0">
          <UTable :data="subscriptions.rows.value" :columns="columns" :loading="subscriptions.pending.value" data-testid="impex-subscriptions-table">
            <template #stream-cell="{ row }">
              <ULink :to="subscriptionPath(row.original.id)" class="font-medium" :data-testid="`impex-subscription-row-${row.original.id}`">{{ row.original.stream }}</ULink>
            </template>
            <template #endpoint-cell="{ row }">
              {{ endpointOf(row.original.endpoint) ?? t('impex.subscriptions.feed_only') }}
            </template>
            <template #status-cell="{ row }">
              <ImpexStatusBadge :status="row.original.status" />
            </template>
            <template #last_delivered_at-cell="{ row }">
              {{ formatDate(row.original.last_delivered_at) ?? t('impex.common.none') }}
            </template>
            <template #empty>
              <AtriumState compact :title="t('impex.subscriptions.empty')" data-testid="impex-subscriptions-empty" />
            </template>
          </UTable>

          <UButton v-if="subscriptions.nextCursor.value" variant="outline" :loading="subscriptions.pending.value" data-testid="impex-load-more" @click="subscriptions.loadMore">
            {{ t('impex.common.load_more') }}
          </UButton>
        </ImpexState>
      </div>
    </AtriumIntegrationScreen>
  </div>
</template>
