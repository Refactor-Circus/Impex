<script setup lang="ts">
import ImpexChannelFields from '../../components/ImpexChannelFields.vue'
import ImpexFlash from '../../components/ImpexFlash.vue'
import ImpexState from '../../components/ImpexState.vue'
import ImpexStatusBadge from '../../components/ImpexStatusBadge.vue'
import { channelBody, emptyChannelForm, looseBody, useImpexAction, useImpexResource } from '../../components/shared'
import type { AtriumDetailsItem } from '@refactor-circus/atrium'
import type { components } from '../../../../client/src/schema'

/**
 * `impex.channel`: one channel (`impex.channels.show`), configured or stored.
 * A stored channel offers v1's actions: update its fields
 * (`impex.channels.update`), rotate its signing secret, shown once
 * (`impex.channels.rotate-secret`), and delete it after a confirmation
 * (`impex.channels.destroy`). A configured channel shows its options.
 */
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const name = computed(() => String(route.params.name ?? ''))

const channel = useImpexResource(async client => integrationData(await client.GET('/channels/{name}', { params: { path: { name: name.value } } })).data, name)

const action = useImpexAction()
const form = reactive(emptyChannelForm())
const confirmingDelete = ref(false)

/** The channel's facts; the flow it starts only when it has one. */
const details = computed<AtriumDetailsItem[]>(() => {
  const value = channel.data.value

  if (!value) {
    return []
  }

  return [
    { key: 'status', label: t('impex.channels.status') },
    { key: 'body_policy', label: t('impex.channel.body_policy'), value: t(`impex.body_policies.${value.body_policy}`) },
    { key: 'signature', label: t('impex.message.signature'), value: value.verifies_signatures ? t('impex.message.verified') : t('impex.message.unsigned') },
    ...(value.flow ? [{ key: 'flow', label: t('impex.channel.flow'), value: value.flow }] : []),
    { key: 'source', label: t('impex.channels.source'), value: value.stored ? t('impex.channels.stored') : t('impex.channels.from_config') }
  ]
})

watch(() => channel.data.value, (value) => {
  if (value) {
    Object.assign(form, emptyChannelForm(value))
  }
})

async function update(): Promise<void> {
  const client = channel.client
  const fields = channelBody(form)

  if (!client) {
    return
  }

  if (fields === null) {
    action.invalid('options', t('impex.channel.options_invalid'))
    return
  }

  const body = looseBody<components['schemas']['UpdateChannelRequest']>(fields)

  await action.run(
    async () => integrationData(await client.PATCH('/channels/{channel}', { params: { path: { channel: name.value } }, body })),
    async () => {
      await channel.reload()
      return t('impex.channel.updated')
    }
  )
}

async function rotateSecret(): Promise<void> {
  const client = channel.client

  if (!client) {
    return
  }

  // Shown once, in the notice, and never again.
  await action.run(
    async () => integrationData(await client.POST('/channels/{channel}/rotate-secret', { params: { path: { channel: name.value } } })),
    async (answer) => {
      await channel.reload()
      return t('impex.channel.secret_rotated', { secret: answer.secret })
    }
  )
}

async function destroy(): Promise<void> {
  const client = channel.client

  if (!client) {
    return
  }

  confirmingDelete.value = false

  await action.run(
    async () => integrationData(await client.DELETE('/channels/{channel}', { params: { path: { channel: name.value } } })),
    async () => {
      await router.push('/impex/channels')
    }
  )
}
</script>

<template>
  <div class="space-y-4" data-testid="impex-channel">
    <AtriumIntegrationScreen screen-key="impex.channel" :record="name">
      <ImpexState :error="channel.error.value" :ready="channel.data.value !== null">
        <div v-if="channel.data.value" class="space-y-4">
          <AtriumPageHeader :title="channel.data.value.name" :description="`${t(`impex.directions.${channel.data.value.direction}`)} · ${channel.data.value.transport}`">
            <template v-if="channel.data.value.stored">
              <UButton variant="outline" icon="i-lucide-key-round" :loading="action.busy.value" data-testid="impex-channel-rotate" @click="rotateSecret">
                {{ t('impex.channel.rotate_secret') }}
              </UButton>
              <UButton v-if="!confirmingDelete" variant="outline" color="error" icon="i-lucide-trash-2" data-testid="impex-channel-delete" @click="() => { confirmingDelete = true }">
                {{ t('impex.channel.delete') }}
              </UButton>
              <template v-else>
                <UButton color="error" icon="i-lucide-trash-2" :loading="action.busy.value" data-testid="impex-channel-delete-confirm" @click="destroy">
                  {{ t('impex.channel.delete_confirm') }}
                </UButton>
                <UButton variant="ghost" data-testid="impex-channel-delete-cancel" @click="() => { confirmingDelete = false }">
                  {{ t('impex.common.cancel') }}
                </UButton>
              </template>
            </template>
          </AtriumPageHeader>

          <ImpexFlash :action="action" />

          <UCard>
            <AtriumDetails :items="details" :columns="2" data-testid="impex-channel-details">
              <template #status>
                <ImpexStatusBadge :status="channel.data.value.status" />
              </template>
            </AtriumDetails>
          </UCard>

          <UCard v-if="channel.data.value.stored">
            <template #header>
              <h2 class="font-medium">{{ t('impex.channel.edit') }}</h2>
            </template>
            <UForm :state="form" class="flex flex-col gap-4" data-testid="impex-channel-update" @submit="update">
              <div class="flex flex-wrap items-end gap-3">
                <ImpexChannelFields :form="form" />
              </div>
              <div>
                <UButton type="submit" :loading="action.busy.value">{{ t('impex.common.save') }}</UButton>
              </div>
            </UForm>
          </UCard>

          <UCard v-else>
            <template #header>
              <h2 class="font-medium">{{ t('impex.channel.options') }}</h2>
            </template>
            <pre class="overflow-x-auto rounded-md bg-elevated p-2 text-xs" data-testid="impex-channel-options">{{ JSON.stringify(channel.data.value.options, null, 2) }}</pre>
            <p class="mt-2 text-sm text-muted">{{ t('impex.channel.configured_hint') }}</p>
          </UCard>
        </div>
      </ImpexState>
    </AtriumIntegrationScreen>
  </div>
</template>
