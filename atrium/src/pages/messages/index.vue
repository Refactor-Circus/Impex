<script setup lang="ts">
import ImpexState from '../../components/ImpexState.vue'
import { CHANNEL_DIRECTIONS, formatDate, messagePath, NUMERIC_COLUMN, queryValue, useImpexList } from '../../components/shared'
import type { components } from '../../../../client/src/schema'
import type { TableColumn } from '@nuxt/ui'

/**
 * `impex.messages`: everything that crossed the boundary, newest first,
 * filtered by direction and channel as v1 was (`impex.messages.index`), a
 * cursor page at a time. The filters live in the URL.
 */
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const filters = computed(() => ({
  direction: queryValue(route.query.direction) as components['schemas']['Direction'] | undefined,
  channel: queryValue(route.query.channel)
}))

const form = reactive({ ...filters.value })

watch(filters, (value) => {
  Object.assign(form, value)
})

const messages = useImpexList(
  async (client, cursor) => integrationData(await client.GET('/messages', { params: { query: { ...filters.value, cursor } } })),
  filters
)

const columns = computed<TableColumn<(typeof messages.rows.value)[number]>[]>(() => [
  { accessorKey: 'direction', header: t('impex.messages.direction') },
  { accessorKey: 'channel', header: t('impex.messages.channel') },
  { accessorKey: 'endpoint', header: t('impex.messages.endpoint'), meta: { class: { td: 'max-w-xs truncate font-mono text-xs' } } },
  { accessorKey: 'status_code', header: t('impex.messages.status_code'), meta: NUMERIC_COLUMN },
  { accessorKey: 'occurred_at', header: t('impex.messages.when') }
])

const directionItems = computed(() => CHANNEL_DIRECTIONS.map(direction => ({ label: t(`impex.directions.${direction}`), value: direction })))

function filter(): void {
  router.replace({ query: { ...route.query, direction: form.direction || undefined, channel: form.channel || undefined } })
}

function clear(): void {
  router.replace({ query: { ...route.query, direction: undefined, channel: undefined } })
}
</script>

<template>
  <div class="space-y-4" data-testid="impex-messages">
    <AtriumPageHeader :title="t('impex.messages.title')" />

    <AtriumIntegrationScreen screen-key="impex.messages">
      <div class="space-y-4">
        <UForm :state="form" class="flex flex-wrap items-center gap-2" data-testid="impex-messages-filters" @submit="filter">
          <USelect v-model="form.direction" :items="directionItems" :placeholder="t('impex.messages.all_directions')" class="w-44" :aria-label="t('impex.messages.direction')" />
          <UInput v-model="form.channel" name="channel" :placeholder="t('impex.messages.channel')" class="w-48" />
          <UButton type="submit" icon="i-lucide-filter" data-testid="impex-messages-filter">{{ t('impex.common.filter') }}</UButton>
          <UButton variant="ghost" data-testid="impex-messages-clear" @click="clear">{{ t('impex.common.clear') }}</UButton>
        </UForm>

        <ImpexState :error="messages.error.value" :ready="!messages.pending.value || messages.rows.value.length > 0">
          <UTable :data="messages.rows.value" :columns="columns" :loading="messages.pending.value" data-testid="impex-messages-table">
            <template #direction-cell="{ row }">
              <ULink :to="messagePath(row.original.id)" class="font-medium" :data-testid="`impex-message-row-${row.original.id}`">
                {{ t(`impex.directions.${row.original.direction}`) }}
              </ULink>
            </template>
            <template #endpoint-cell="{ row }">
              {{ row.original.method }} {{ row.original.endpoint }}
            </template>
            <template #status_code-cell="{ row }">
              {{ row.original.status_code ?? t('impex.common.none') }}
            </template>
            <template #occurred_at-cell="{ row }">
              {{ formatDate(row.original.occurred_at) }}
            </template>
            <template #empty>
              <AtriumState compact :title="t('impex.messages.empty')" data-testid="impex-messages-empty" />
            </template>
          </UTable>

          <UButton v-if="messages.nextCursor.value" variant="outline" :loading="messages.pending.value" data-testid="impex-load-more" @click="messages.loadMore">
            {{ t('impex.common.load_more') }}
          </UButton>
        </ImpexState>
      </div>
    </AtriumIntegrationScreen>
  </div>
</template>
