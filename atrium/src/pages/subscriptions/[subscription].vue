<script setup lang="ts">
import ImpexFlash from '../../components/ImpexFlash.vue'
import ImpexState from '../../components/ImpexState.vue'
import ImpexStatusBadge from '../../components/ImpexStatusBadge.vue'
import { endpointOf, formatDate, isOn, messagePath, NUMERIC_COLUMN, useImpexAction, useImpexResource } from '../../components/shared'
import type { components } from '../../../../client/src/schema'
import type { TableColumn } from '@nuxt/ui'

/**
 * `impex.subscription`: one subscription (`impex.subscriptions.show`) with
 * its recent deliveries (`impex.subscriptions.deliveries`), and v1's
 * actions: pause and resume (`impex.subscriptions.update` with `status`),
 * replay from an event (`update` with `replay_from`) and, for one that
 * pushes, ping (`impex.subscriptions.ping`). Each action reloads it.
 */
const { t } = useI18n()
const route = useRoute()

const id = computed(() => String(route.params.subscription ?? ''))

const subscription = useImpexResource(async client => integrationData(await client.GET('/subscriptions/{subscription}', { params: { path: { subscription: id.value } } })).data, id)

const deliveries = useImpexResource(async client => integrationData(await client.GET('/subscriptions/{subscription}/deliveries', { params: { path: { subscription: id.value }, query: { per_page: 20 } } })).data, id)

const action = useImpexAction()
const replayFrom = ref<string | number>('')

const deliveryColumns = computed<TableColumn<NonNullable<typeof deliveries.data.value>[number]>[]>(() => [
  { accessorKey: 'status', header: t('impex.subscriptions.status') },
  { accessorKey: 'events', header: t('impex.subscription.events'), meta: NUMERIC_COLUMN },
  { accessorKey: 'duration_ms', header: t('impex.message.duration'), meta: NUMERIC_COLUMN },
  { accessorKey: 'attempted_at', header: t('impex.messages.when') }
])

const endpoint = computed(() => endpointOf(subscription.data.value?.endpoint))
const lastError = computed(() => {
  const message = subscription.data.value?.last_error?.message

  return typeof message === 'string' ? message : null
})

async function reload(): Promise<void> {
  await Promise.all([subscription.reload(), deliveries.reload()])
}

async function update(body: components['schemas']['UpdateSubscriptionRequest'], notice: string): Promise<void> {
  const client = subscription.client

  if (!client) {
    return
  }

  await action.run(
    async () => integrationData(await client.PATCH('/subscriptions/{subscription}', { params: { path: { subscription: id.value } }, body })),
    async () => {
      await reload()
      return notice
    }
  )
}

async function pause(): Promise<void> {
  await update({ status: 'paused' }, t('impex.subscription.paused'))
}

async function resume(): Promise<void> {
  await update({ status: 'active' }, t('impex.subscription.resumed'))
}

async function replay(): Promise<void> {
  // A number input may hand back a number or the typed text.
  const raw = String(replayFrom.value).trim()
  const from = Number(raw)

  if (raw === '' || !Number.isInteger(from) || from < 0) {
    action.invalid('replay_from', t('impex.subscription.replay_invalid'))
    return
  }

  await update({ replay_from: from }, t('impex.subscription.replaying'))
  replayFrom.value = ''
}

async function ping(): Promise<void> {
  const client = subscription.client

  if (!client) {
    return
  }

  await action.run(
    async () => integrationData(await client.POST('/subscriptions/{subscription}/ping', { params: { path: { subscription: id.value } } })),
    async (answer) => {
      await reload()

      return isOn(answer.data.successful)
        ? t('impex.subscription.ping_succeeded')
        : t('impex.subscription.ping_failed', { error: String(answer.data.error ?? '') })
    }
  )
}
</script>

<template>
  <div class="space-y-4" data-testid="impex-subscription">
    <AtriumIntegrationScreen screen-key="impex.subscription" :record="id">
      <ImpexState :error="subscription.error.value" :ready="subscription.data.value !== null">
        <div v-if="subscription.data.value" class="space-y-4">
          <AtriumPageHeader :title="subscription.data.value.stream">
            <template #meta>
              <ImpexStatusBadge :status="subscription.data.value.status" />
            </template>
            <UButton v-if="subscription.data.value.status === 'active'" variant="outline" icon="i-lucide-pause" :loading="action.busy.value" data-testid="impex-subscription-pause" @click="pause">
              {{ t('impex.subscription.pause') }}
            </UButton>
            <UButton v-else variant="outline" icon="i-lucide-play" :loading="action.busy.value" data-testid="impex-subscription-resume" @click="resume">
              {{ t('impex.subscription.resume') }}
            </UButton>
            <UButton v-if="endpoint" variant="outline" icon="i-lucide-send" :loading="action.busy.value" data-testid="impex-subscription-ping" @click="ping">
              {{ t('impex.subscription.ping') }}
            </UButton>
          </AtriumPageHeader>

          <ImpexFlash :action="action" />

          <UCard>
            <dl class="grid gap-3 text-sm sm:grid-cols-2" data-testid="impex-subscription-details">
              <div><dt class="text-muted">{{ t('impex.subscription.topics') }}</dt><dd>{{ subscription.data.value.topics.join(', ') || t('impex.common.none') }}</dd></div>
              <div><dt class="text-muted">{{ t('impex.subscription.selection') }}</dt><dd>{{ subscription.data.value.selection }}</dd></div>
              <div><dt class="text-muted">{{ t('impex.subscription.format') }}</dt><dd>{{ subscription.data.value.format }}</dd></div>
              <div><dt class="text-muted">{{ t('impex.subscriptions.endpoint') }}</dt><dd>{{ endpoint ?? t('impex.subscriptions.feed_only') }}</dd></div>
              <div><dt class="text-muted">{{ t('impex.subscription.cursor') }}</dt><dd class="font-mono">{{ subscription.data.value.cursor }}</dd></div>
              <div><dt class="text-muted">{{ t('impex.subscriptions.failures') }}</dt><dd class="tabular-nums">{{ subscription.data.value.failures }}</dd></div>
              <div v-if="subscription.data.value.paused_until"><dt class="text-muted">{{ t('impex.subscription.paused_until') }}</dt><dd>{{ formatDate(subscription.data.value.paused_until) }}</dd></div>
              <div v-if="lastError" class="sm:col-span-2"><dt class="text-muted">{{ t('impex.subscription.last_error') }}</dt><dd>{{ lastError }}</dd></div>
            </dl>
          </UCard>

          <UCard>
            <UForm :state="{ replay_from: replayFrom }" class="flex flex-wrap items-end gap-3" data-testid="impex-subscription-replay" @submit="replay">
              <UFormField :label="t('impex.subscription.replay_from')" class="w-48">
                <UInput v-model="replayFrom" name="replay_from" type="number" min="0" />
              </UFormField>
              <UButton type="submit" icon="i-lucide-rotate-ccw" :loading="action.busy.value">{{ t('impex.subscription.replay') }}</UButton>
            </UForm>
          </UCard>

          <UCard>
            <template #header>
              <h2 class="font-medium">{{ t('impex.subscription.deliveries') }}</h2>
            </template>
            <ImpexState :error="deliveries.error.value" :ready="deliveries.data.value !== null">
              <UTable :data="deliveries.data.value ?? []" :columns="deliveryColumns" data-testid="impex-subscription-deliveries">
                <template #status-cell="{ row }">
                  <span class="flex items-center gap-1.5" :data-testid="`impex-delivery-${row.original.id}`">
                    <ImpexStatusBadge :status="row.original.status" />
                    <span v-if="row.original.status_code" class="text-xs text-muted">{{ row.original.status_code }}</span>
                  </span>
                </template>
                <template #duration_ms-cell="{ row }">
                  {{ row.original.duration_ms === null ? t('impex.common.none') : t('impex.message.milliseconds', { count: row.original.duration_ms }) }}
                </template>
                <template #attempted_at-cell="{ row }">
                  <ULink v-if="row.original.message_id" :to="messagePath(row.original.message_id)">{{ formatDate(row.original.attempted_at) }}</ULink>
                  <span v-else>{{ formatDate(row.original.attempted_at) }}</span>
                </template>
                <template #empty>
                  <AtriumState compact :title="t('impex.subscription.no_deliveries')" data-testid="impex-subscription-no-deliveries" />
                </template>
              </UTable>
            </ImpexState>
          </UCard>
        </div>
      </ImpexState>
    </AtriumIntegrationScreen>
  </div>
</template>
