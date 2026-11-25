<script setup lang="ts">
import ImpexState from '../../components/ImpexState.vue'
import { formatDate, runPath, useImpexResource } from '../../components/shared'
import type { AtriumDetailsItem } from '@refactor-circus/atrium'

/**
 * `impex.message`: one ledger entry (`impex.messages.show`), with its
 * headers, its body preview and the run it belongs to.
 */
const { t } = useI18n()
const route = useRoute()

const id = computed(() => String(route.params.message ?? ''))

const message = useImpexResource(async client => integrationData(await client.GET('/messages/{message}', { params: { path: { message: id.value } } })).data, id)

const signature = computed(() => {
  const valid = message.data.value?.signature_valid

  return valid === true ? t('impex.message.verified') : valid === false ? t('impex.message.bad_signature') : t('impex.message.unsigned')
})

/** The message's facts; the run it belongs to only when it has one. */
const details = computed<AtriumDetailsItem[]>(() => {
  const value = message.data.value

  if (!value) {
    return []
  }

  return [
    { key: 'when', label: t('impex.messages.when'), value: formatDate(value.occurred_at) },
    { key: 'transport', label: t('impex.messages.transport'), value: value.transport },
    { key: 'status_code', label: t('impex.messages.status_code'), value: value.status_code },
    { key: 'duration', label: t('impex.message.duration'), value: value.duration_ms === null ? null : t('impex.message.milliseconds', { count: value.duration_ms }) },
    { key: 'size', label: t('impex.message.size'), value: t('impex.message.bytes', { count: value.bytes }) },
    { key: 'signature', label: t('impex.message.signature'), value: signature.value, testid: 'impex-message-signature' },
    ...(value.run_id ? [{ key: 'run', label: t('impex.message.run'), wide: true }] : [])
  ]
})
</script>

<template>
  <div class="space-y-4" data-testid="impex-message">
    <AtriumIntegrationScreen screen-key="impex.message" :record="id">
      <ImpexState :error="message.error.value" :ready="message.data.value !== null">
        <div v-if="message.data.value" class="space-y-4">
          <AtriumPageHeader :title="`${t(`impex.directions.${message.data.value.direction}`)} · ${message.data.value.channel}`">
            <template #meta>
              <p class="font-mono">{{ message.data.value.method }} {{ message.data.value.endpoint }}</p>
            </template>
          </AtriumPageHeader>

          <UCard>
            <AtriumDetails :items="details" :columns="2" :empty="t('impex.common.none')" data-testid="impex-message-details">
              <template #run>
                <ULink :to="runPath(message.data.value.run_id ?? '')" class="atrium-mono" data-testid="impex-message-run">{{ message.data.value.run_id }}</ULink>
              </template>
            </AtriumDetails>
          </UCard>

          <UCard v-if="message.data.value.headers && Object.keys(message.data.value.headers).length">
            <template #header>
              <h2 class="font-medium">{{ t('impex.message.headers') }}</h2>
            </template>
            <pre class="overflow-x-auto text-xs">{{ JSON.stringify(message.data.value.headers, null, 2) }}</pre>
          </UCard>

          <UCard>
            <template #header>
              <h2 class="font-medium">{{ t('impex.message.body') }}</h2>
            </template>
            <pre class="overflow-x-auto whitespace-pre-wrap text-xs" data-testid="impex-message-body">{{ message.data.value.body_preview || t('impex.message.empty_body') }}</pre>
            <p v-if="message.data.value.body_artifact_id" class="mt-2 text-sm text-muted">
              {{ t('impex.message.body_on_disk', { id: message.data.value.body_artifact_id }) }}
            </p>
          </UCard>

          <UCard v-if="message.data.value.error">
            <template #header>
              <h2 class="font-medium">{{ t('impex.run.error') }}</h2>
            </template>
            <pre class="overflow-x-auto text-xs">{{ JSON.stringify(message.data.value.error, null, 2) }}</pre>
          </UCard>
        </div>
      </ImpexState>
    </AtriumIntegrationScreen>
  </div>
</template>
