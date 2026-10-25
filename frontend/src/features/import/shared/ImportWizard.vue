<script setup lang="ts">
import { computed, ref } from 'vue'

export type ImportMode = 'CREATE_ONLY' | 'UPDATE_ONLY' | 'UPSERT'

const props = defineProps<{
  canStart: boolean
  busy: boolean
  templateVersion: string
  templatePrefix: string
  labels: {
    stepFile: string
    stepDescription: string
    file: string
    noFile: string
    mode: string
    modeCreateOnly: string
    modeUpdateOnly: string
    modeUpsert: string
    fileRequired: string
    invalidFileType: string
    formulaRejected: string
    noPermission: string
    start: string
    starting: string
    templates: string
    templateCsv: string
    templateXlsx: string
    sizeExceeded: string
  }
}>()

const emit = defineEmits<{ submit: [file: File, mode: ImportMode] }>()
const modes: ImportMode[] = ['CREATE_ONLY', 'UPDATE_ONLY', 'UPSERT']
const file = ref<File | null>(null)
const mode = ref<ImportMode>('CREATE_ONLY')
const clientError = ref('')
const templateRoot = computed(() => `${import.meta.env.BASE_URL}import-templates/`)

function changeFile(event: Event): void {
  const selected = (event.target as HTMLInputElement).files?.[0] ?? null
  file.value = selected
  clientError.value = ''
  if (selected && !/\.(csv|xlsx)$/i.test(selected.name)) {
    clientError.value = props.labels.invalidFileType
    file.value = null
  } else if (selected && selected.size > 2 * 1024 * 1024) {
    clientError.value = props.labels.sizeExceeded
    file.value = null
  }
}

async function hasCsvFormula(candidate: File): Promise<boolean> {
  if (!/\.csv$/i.test(candidate.name)) return false
  const contents = await candidate.text()
  // Convenience only: backend enforces canonical parser and formula rejection.
  return contents.split(/\r?\n/).some(line =>
    line.split(',').some(cell => /^\s*[=+@]/.test(cell) || /^\s*-(?!\d)/.test(cell)),
  )
}

async function submit(): Promise<void> {
  clientError.value = ''
  if (!file.value) {
    clientError.value = props.labels.fileRequired
    return
  }
  if (!props.canStart) {
    clientError.value = props.labels.noPermission
    return
  }
  if (await hasCsvFormula(file.value)) {
    clientError.value = props.labels.formulaRejected
    return
  }
  emit('submit', file.value, mode.value)
}
</script>

<template>
  <form class="import-wizard" @submit.prevent="submit">
    <section class="download-templates" :aria-label="labels.templates">
      <h2>{{ labels.templates }} (v{{ templateVersion }})</h2>
      <a :href="`${templateRoot}${templatePrefix}-v${templateVersion}.xlsx`" download class="template-link">
        {{ labels.templateXlsx }}
      </a>
      <a :href="`${templateRoot}${templatePrefix}-v${templateVersion}.csv`" download class="template-link">
        {{ labels.templateCsv }}
      </a>
    </section>

    <div class="step-heading">
      <span class="step-number">1</span>
      <div><h2>{{ labels.stepFile }}</h2><p>{{ labels.stepDescription }}</p></div>
    </div>
    <label class="file-field">
      <span>{{ labels.file }}</span>
      <input type="file"
        accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        :disabled="busy" @change="changeFile" />
      <small>{{ file?.name ?? labels.noFile }}</small>
    </label>
    <fieldset>
      <legend>{{ labels.mode }}</legend>
      <label v-for="candidate in modes" :key="candidate">
        <input v-model="mode" type="radio" name="import-mode" :value="candidate" :disabled="busy" />
        <span>{{ candidate === 'CREATE_ONLY' ? labels.modeCreateOnly
          : candidate === 'UPDATE_ONLY' ? labels.modeUpdateOnly : labels.modeUpsert }}</span>
      </label>
    </fieldset>
    <p v-if="clientError" class="inline-error" role="alert">{{ clientError }}</p>
    <p v-if="!canStart && !clientError" class="permission-note" role="status">{{ labels.noPermission }}</p>
    <footer><button type="submit" class="primary-button" :disabled="busy || !canStart">
      {{ busy ? labels.starting : labels.start }}
    </button></footer>
  </form>
</template>

<style scoped>
.import-wizard { display:grid;gap:20px;padding:20px;border:1px solid #dbe5ea;border-radius:6px;background:#fff; }
.download-templates {display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px;border:1px dashed #bed2df;border-radius:6px;}
.download-templates h2 {margin:0;font-size:14px;font-weight:600;flex-basis:100%;}
.template-link {display:inline-flex;border:1px solid #1876a8;border-radius:4px;padding:8px 12px;color:#145a7e;text-decoration:none;font-weight:600;}
.template-link:hover {background:#f4f8fa;text-decoration:underline;}
.step-heading {display:flex;align-items:center;gap:12px;}
.step-heading h2 {margin:0;}
.step-heading p {margin:4px 0 0;color:#536b7b;}
.step-number {display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:#1876a8;color:#fff;font-weight:700;}
.file-field,fieldset {display:grid;gap:8px;}
.file-field span,legend {color:#294b65;font-size:13px;font-weight:700;}
.file-field input {min-height:38px;}
.file-field small {color:#536b7b;}
fieldset {grid-template-columns:repeat(3,minmax(0,1fr));border:0;padding:0;}
legend {grid-column:1/-1;}
fieldset label {display:flex;align-items:center;gap:8px;min-height:40px;padding:0 10px;border:1px solid #c7d6df;border-radius:4px;}
.inline-error {margin:0;color:#b64444;}
.permission-note {margin:0;color:#8a5a00;}
footer {display:flex;justify-content:flex-end;}
.primary-button {min-height:36px;border:1px solid #1876a8;border-radius:4px;padding:7px 14px;cursor:pointer;background:#1876a8;color:#fff;}
.primary-button:disabled {opacity:.55;cursor:not-allowed;}
@media(max-width:640px) {fieldset {grid-template-columns:1fr;}}
</style>
