<script setup lang="ts">
import type { TableColumn } from '@refactor-circus/atrium/ui'
import ImpexState from '../../components/ImpexState.vue'
import ImpexStatusBadge from '../../components/ImpexStatusBadge.vue'
import { channelPath, useImpexResource } from '../../components/shared'

/**
 * `impex.channels`: every channel, configured or stored
 * (`impex.channels.index`), each opening its page, and v1's "New channel".
 */
const { t } = useI18n()

const channels = useImpexResource(async client => integrationData(await client.GET('/channels')).data)

const columns = computed<TableColumn<NonNullable<typeof channels.data.value>[number]>[]>(() => [
  { accessorKey: 'name', header: t('impex.channels.name') },
  { accessorKey: 'direction', header: t('impex.channels.direction') },
  { accessorKey: 'transport', header: t('impex.channels.transport') },
  { accessorKey: 'status', header: t('impex.channels.status') },
  { accessorKey: 'stored', header: t('impex.channels.source') }
])
</script>

<template>
  <div class="space-y-4" data-testid="impex-channels">
    <AtriumPageHeader :title="t('impex.channels.title')">
      <UButton to="/impex/channels/create" icon="i-lucide-plus" data-testid="impex-new-channel">{{ t('impex.channels.new') }}</UButton>
    </AtriumPageHeader>

    <AtriumIntegrationScreen screen-key="impex.channels">
      <ImpexState :error="channels.error.value" :ready="channels.data.value !== null">
        <UTable :data="channels.data.value ?? []" :columns="columns" data-testid="impex-channels-table">
          <template #name-cell="{ row }">
            <ULink :to="channelPath(row.original.name)" class="font-medium" :data-testid="`impex-channel-row-${row.original.name}`">{{ row.original.name }}</ULink>
          </template>
          <template #direction-cell="{ row }">
            {{ t(`impex.directions.${row.original.direction}`) }}
          </template>
          <template #status-cell="{ row }">
            <ImpexStatusBadge :status="row.original.status" />
          </template>
          <template #stored-cell="{ row }">
            {{ row.original.stored ? t('impex.channels.stored') : t('impex.channels.from_config') }}
          </template>
          <template #empty>
            <AtriumState compact :title="t('impex.channels.empty')" data-testid="impex-channels-empty" />
          </template>
        </UTable>
      </ImpexState>
    </AtriumIntegrationScreen>
  </div>
</template>
