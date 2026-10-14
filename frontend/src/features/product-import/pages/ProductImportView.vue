<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import type { ProductImportStart } from '@/api/generated/types.gen'
import { useBasePermission } from '@/composables/useBasePermission'
import { reportApiError } from '@/services/errorBoundary'
import { startProductImportJob } from '@/services/productImport'

const { t } = useI18n()
const router = useRouter()
const { hasBasePermission, snapshotState } = useBasePermission()
const modes = ['CREATE_ONLY', 'UPDATE_ONLY', 'UPSERT'] as const

const file = ref<File | null>(null)
const mode = ref<ProductImportStart['mode']>('CREATE_ONLY')
const busy = ref(false)
const error = ref<unknown | null>(null)
const clientError = ref('')

const canStart = computed(
  () => hasBasePermission('product', 'import') && snapshotState.value === 'ready',
)

function onFileChange(event: Event): void {
  const selected = (event.target as HTMLInputElement).files?.[0] ?? null
  file.value = selected
  clientError.value = ''
  error.value = null
  if (selected && !/\.(csv|xlsx)$/i.test(selected.name)) {
    clientError.value = t('productImport.invalidFileType')
    file.value = null
  }
}

function hasCsvFormula(candidate: File): Promise<boolean> {
  if (!/\.csv$/i.test(candidate.name)) return Promise.resolve(false)
  return candidate
    .text()
    .then((value) =>
      value.split(/\r?\n/).some((line) => line.split(',').some((cell) => /^\s*=/.test(cell))),
    )
}

async function submit(): Promise<void> {
  clientError.value = ''
  error.value = null
  if (!file.value) {
    clientError.value = t('productImport.fileRequired')
    return
  }
  if (!canStart.value) {
    clientError.value = t('productImport.noPermission')
    return
  }
  if (await hasCsvFormula(file.value)) {
    clientError.value = t('productImport.formulaRejected')
    return
  }
  busy.value = true
  try {
    const job = await startProductImportJob(file.value, mode.value)
    await router.push(`/products/import-jobs/${String(job.id)}`)
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="import-panel">
    <header class="page-header">
      <div>
        <p class="eyebrow">{{ t('productImport.eyebrow') }}</p>
        <h1>{{ t('productImport.title') }}</h1>
        <p class="page-description">{{ t('productImport.description') }}</p>
      </div>
      <button type="button" class="secondary-button" @click="router.push('/products/import-jobs')">
        {{ t('productImport.viewJobs') }}
      </button>
    </header>

    <form class="wizard-form" @submit.prevent="submit">
      <div class="step-heading">
        <span class="step-number">1</span>
        <div>
          <h2>{{ t('productImport.stepFile') }}</h2>
          <p>{{ t('productImport.stepDescription') }}</p>
        </div>
      </div>

      <label class="file-field">
        <span>{{ t('productImport.file') }}</span>
        <input
          type="file"
          accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
          :disabled="busy"
          @change="onFileChange"
        />
        <small>{{ file?.name ?? t('productImport.noFile') }}</small>
      </label>

      <fieldset>
        <legend>{{ t('productImport.mode') }}</legend>
        <label v-for="option in modes" :key="option">
          <input v-model="mode" type="radio" name="import-mode" :value="option" :disabled="busy" />
          <span>{{ option }}</span>
        </label>
      </fieldset>

      <aside class="guidance" :aria-label="t('productImport.guidanceTitle')">
        <h2>{{ t('productImport.guidanceTitle') }}</h2>
        <ul>
          <li>{{ t('productImport.guidanceFormat') }}</li>
          <li>{{ t('productImport.guidanceColumns') }}</li>
          <li>{{ t('productImport.guidanceSourceKey') }}</li>
          <li>{{ t('productImport.guidanceCode') }}</li>
          <li>{{ t('productImport.guidanceNull') }}</li>
          <li>{{ t('productImport.guidanceFormula') }}</li>
        </ul>
      </aside>

      <p v-if="clientError" class="inline-error" role="alert">{{ clientError }}</p>
      <p v-if="error" class="inline-error" role="alert">{{ t('productImport.requestError') }}</p>
      <p v-if="!canStart && !clientError" class="permission-note" role="status">
        {{ t('productImport.noPermission') }}
      </p>
      <footer class="form-actions">
        <button
          type="button"
          class="secondary-button"
          :disabled="busy"
          @click="router.push('/products')"
        >
          {{ t('common.cancel') }}
        </button>
        <button type="submit" class="primary-button" :disabled="busy || !canStart">
          {{ busy ? t('productImport.starting') : t('productImport.start') }}
        </button>
      </footer>
    </form>
  </section>
</template>

<style scoped>
.import-panel {
  display: grid;
  gap: 18px;
  padding: 18px 20px 24px;
  max-width: 920px;
}
.page-header,
.form-actions,
.step-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.page-header h1,
.step-heading h2,
.guidance h2 {
  margin: 0;
}
.page-description,
.step-heading p {
  margin: 6px 0 0;
  color: #536b7b;
}
.eyebrow {
  margin: 0 0 4px;
  color: #1876a8;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.wizard-form {
  display: grid;
  gap: 20px;
  padding: 20px;
  border: 1px solid #dbe5ea;
  border-radius: 6px;
  background: #fff;
}
.step-heading {
  justify-content: flex-start;
}
.step-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: #1876a8;
  color: #fff;
  font-weight: 700;
}
.file-field,
fieldset {
  display: grid;
  gap: 8px;
}
.file-field > span,
legend {
  color: #294b65;
  font-size: 13px;
  font-weight: 700;
}
.file-field input {
  min-height: 38px;
}
.file-field small {
  color: #536b7b;
}
fieldset {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  border: 0;
  padding: 0;
}
legend {
  grid-column: 1 / -1;
  padding: 0;
}
fieldset label {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 40px;
  padding: 0 10px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
}
.guidance {
  padding: 14px 16px;
  border-left: 3px solid #1876a8;
  background: #f4f8fa;
}
.guidance ul {
  display: grid;
  gap: 6px;
  margin: 10px 0 0;
  padding-left: 20px;
  color: #536b7b;
  font-size: 13px;
}
.inline-error {
  margin: 0;
  color: #b64444;
}
.permission-note {
  margin: 0;
  color: #8a5a00;
}
.primary-button,
.secondary-button {
  min-height: 36px;
  border: 1px solid #1876a8;
  border-radius: 4px;
  padding: 7px 14px;
  cursor: pointer;
}
.primary-button {
  background: #1876a8;
  color: #fff;
}
.secondary-button {
  background: #fff;
  color: #294b65;
}
button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}
@media (max-width: 640px) {
  .page-header,
  .form-actions {
    align-items: stretch;
    flex-direction: column;
  }
  fieldset {
    grid-template-columns: 1fr;
  }
}
</style>
