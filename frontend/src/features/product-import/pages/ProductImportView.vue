<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { useBasePermission } from '@/composables/useBasePermission'
import { reportApiError } from '@/services/errorBoundary'
import { startProductImportJob } from '@/services/productImport'
import ImportWizard from '@/features/import/shared/ImportWizard.vue'
import type { ImportMode } from '@/features/import/shared/ImportWizard.vue'
import type { ImportJob } from '@/api/generated/types.gen'

const { t } = useI18n()
const router = useRouter()
const { hasBasePermission, snapshotState } = useBasePermission()
const busy = ref(false)
const error = ref<unknown | null>(null)
const resultJob = ref<ImportJob | null>(null)
const canStart = computed(() => hasBasePermission('product', 'import') && snapshotState.value === 'ready')

const labels = computed(() => Object.fromEntries([
  'stepFile','stepDescription','file','noFile','mode','modeCreateOnly','modeUpdateOnly',
  'modeUpsert','fileRequired','invalidFileType','formulaRejected','noPermission',
  'start','starting','templates','templateCsv','templateXlsx','sizeExceeded',
].map(key => [key, t(`productImport.${key}`)])) as {
  stepFile: string; stepDescription: string; file: string; noFile: string; mode: string;
  modeCreateOnly: string; modeUpdateOnly: string; modeUpsert: string;
  fileRequired: string; invalidFileType: string; formulaRejected: string;
  noPermission: string; start: string; starting: string; templates: string;
  templateCsv: string; templateXlsx: string; sizeExceeded: string;
})

async function submit(file: File, mode: ImportMode): Promise<void> {
  error.value = null
  resultJob.value = null
  if (!canStart.value) return
  busy.value = true
  try {
    const job = await startProductImportJob(file, mode)
    resultJob.value = job
    // Import permission and ImportJob.view are distinct, independently
    // authorized capabilities. Do not navigate into a known denied read.
    if (hasBasePermission('import_job', 'view')) {
      await router.push(`/products/import-jobs/${String(job.id)}`)
    }
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
      <button data-ds-icon="back" type="button" class="secondary-button" @click="router.push('/products/import-jobs')">
        {{ t('productImport.viewJobs') }}
      </button>
    </header>

    <ImportWizard :can-start="canStart" :busy="busy" :labels="labels"
      template-version="1" template-prefix="product" @submit="submit" />
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
    <p v-if="resultJob" role="status" class="import-summary">
      {{ t('productImport.importSummary', {
        id: String(resultJob.id), status: resultJob.status,
        success: resultJob.success_rows, total: resultJob.total_rows, errors: resultJob.error_rows,
      }) }}
    </p>
    <p v-if="error" role="alert" class="inline-error">{{ t('productImport.requestError') }}</p>
    <button data-ds-icon="cancel" type="button" class="secondary-button cancel" @click="router.push('/products')">
      {{ t('common.cancel') }}
    </button>
  </section>
</template>

<style scoped>
.import-panel {display:grid;gap:18px;padding:18px 20px 24px;max-width:920px;}
.page-header {display:flex;align-items:center;justify-content:space-between;gap:16px;}
.page-header h1,.guidance h2 {margin:0;}
.page-description {margin:6px 0 0;color:var(--ds-text-secondary);}
.eyebrow {margin:0 0 4px;color:var(--ds-primary);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;}
.guidance {padding:14px 16px;border-left:3px solid var(--ds-primary);background:var(--ds-primary-soft);}
.guidance ul {display:grid;gap:6px;margin:10px 0 0;padding-left:20px;color:var(--ds-text-secondary);font-size:13px;}
.inline-error {margin:0;color:var(--ds-danger);}
.import-summary {margin:0;padding:12px;border:1px solid var(--ds-success);background:var(--ds-success-soft);color:var(--ds-success);}
.cancel {justify-self:end;}
@media(max-width:640px){.page-header {align-items:stretch;flex-direction:column;}}
</style>
