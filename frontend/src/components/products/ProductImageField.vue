<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { deleteProductImage, fetchProductImage, uploadProductImage } from '@/services/productImage'

const props = withDefaults(
  defineProps<{
    productId?: string
    imageAvailable?: boolean
    editable?: boolean
    disabled?: boolean
  }>(),
  { productId: undefined, imageAvailable: false, editable: false, disabled: false },
)
const { t } = useI18n()
const emit = defineEmits<{ dirty: [value: boolean] }>()
const selectedFile = ref<File | null>(null)
const imageUrl = ref<string | null>(null)
const removing = ref(false)
const loading = ref(false)
const error = ref<string | null>(null)
let generation = 0

function releaseImage(): void {
  if (imageUrl.value) URL.revokeObjectURL(imageUrl.value)
  imageUrl.value = null
}

async function loadImage(): Promise<void> {
  const current = ++generation
  releaseImage()
  if (!props.productId || !props.imageAvailable) return
  loading.value = true
  try {
    const image = await fetchProductImage(props.productId)
    if (current !== generation) return
    imageUrl.value = URL.createObjectURL(image)
  } catch {
    if (current === generation) error.value = t('product.imageLoadError')
  } finally {
    if (current === generation) loading.value = false
  }
}

function chooseImage(event: Event): void {
  const control = event.target as HTMLInputElement
  const file = control.files?.[0] ?? null
  control.value = ''
  if (!file) return
  if (
    !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
    file.size > 3 * 1024 * 1024 ||
    file.size === 0
  ) {
    error.value = t('product.imageInvalid')
    return
  }
  ++generation
  loading.value = false
  releaseImage()
  imageUrl.value = URL.createObjectURL(file)
  selectedFile.value = file
  removing.value = false
  error.value = null
  emit('dirty', true)
}

function removeImage(): void {
  ++generation
  loading.value = false
  releaseImage()
  selectedFile.value = null
  // Removing an unsaved selection is not a server-side DELETE. Only an
  // already persisted image should schedule a deletion on Product save.
  removing.value = Boolean(props.productId && props.imageAvailable)
  error.value = null
  emit('dirty', removing.value)
}

/** Called only after the enclosing Product JSON CREATE/PATCH succeeded. */
async function commit(productId: string): Promise<boolean> {
  if (!selectedFile.value && !removing.value) return false
  error.value = null
  try {
    if (selectedFile.value) await uploadProductImage(productId, selectedFile.value)
    else if (removing.value) await deleteProductImage(productId)
    selectedFile.value = null
    removing.value = false
    emit('dirty', false)
    // Parent refreshes Product metadata; keep preview while save settles.
    return true
  } catch (caught) {
    error.value = t('product.imageSaveError')
    throw caught
  }
}

watch(
  () => [props.productId, props.imageAvailable] as const,
  () => {
    selectedFile.value = null
    removing.value = false
    error.value = null
    emit('dirty', false)
    void loadImage()
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  ++generation
  releaseImage()
})
defineExpose({ commit })
</script>

<template>
  <div class="product-image-field">
    <div class="product-image-preview" :aria-label="t('product.image')">
      <img v-if="imageUrl && !removing" :src="imageUrl" :alt="t('product.image')" />
      <span v-else class="product-image-placeholder">{{ t('product.imageEmpty') }}</span>
    </div>
    <div class="product-image-controls">
      <strong>{{ t('product.image') }}</strong>
      <p class="product-image-hint">{{ t('product.imageHint') }}</p>
      <div v-if="editable" class="product-image-actions">
        <label class="image-upload-button" :class="{ disabled: disabled }">
          {{ t('product.imageChoose') }}
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            :aria-label="t('product.imageChoose')"
            :disabled="disabled"
            @change="chooseImage"
          />
        </label>
        <button
          v-if="imageUrl || selectedFile || (imageAvailable && !removing)"
          class="image-remove-button"
          type="button"
          :disabled="disabled"
          @click="removeImage"
        >
          {{ t('product.imageRemove') }}
        </button>
      </div>
      <small v-if="selectedFile || removing" class="product-image-hint">{{
        t('product.imageSaveHint')
      }}</small>
      <p v-if="loading" class="product-image-hint" role="status">{{ t('common.loading') }}</p>
      <p v-if="error" class="product-image-error" role="alert">{{ error }}</p>
    </div>
  </div>
</template>

<style scoped>
.product-image-field {
  display: flex;
  gap: 16px;
  align-items: flex-start;
  flex-wrap: wrap;
}
.product-image-preview {
  width: 160px;
  height: 140px;
  border: 1px solid #d9e3ea;
  background: #f6f9fb;
  border-radius: 8px;
  display: grid;
  place-items: center;
  overflow: hidden;
  flex-shrink: 0;
}
.product-image-preview img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.product-image-placeholder {
  color: #62798b;
  font-size: 12px;
  padding: 12px;
  text-align: center;
}
.product-image-controls {
  min-width: 200px;
  flex: 1;
}
.product-image-controls strong {
  color: #173c59;
}
.product-image-hint {
  margin: 6px 0;
  color: #536b7b;
  font-size: 12px;
}
.product-image-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 12px;
}
.image-upload-button,
.image-remove-button {
  position: relative;
  display: inline-flex;
  align-items: center;
  min-height: 34px;
  padding: 6px 10px;
  background: #fff;
  border: 1px solid #c7d6df;
  border-radius: 5px;
  font: inherit;
  font-size: 13px;
  color: #1876a8;
  cursor: pointer;
}
.image-upload-button input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}
.image-upload-button:focus-within {
  outline: 2px solid #1876a8;
  outline-offset: 2px;
}
.image-upload-button.disabled,
.image-remove-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.image-remove-button {
  color: #b64444;
}
.product-image-error {
  color: #b64444;
  font-size: 12px;
}
</style>
