<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { deleteProductImage, uploadProductImage } from '@/services/productImage'
import { uploadImageUrl } from '@/services/uploadImageUrl'
import type { Product } from '@/api/generated/types.gen'

const props = withDefaults(
  defineProps<{
    productId?: string
    imageAvailable?: boolean
    imagePath?: string | null
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
const staticFailed = ref(false)
// A newly created Product can acquire its ID after commit(). That transition
// is not a switch to another record and must not discard a failed upload.
let adoptedProductId: string | null = null
const storedUrl = computed(() => uploadImageUrl(props.imagePath, 'product'))
const hasStoredImage = computed(() => Boolean(storedUrl.value) || props.imageAvailable)
const staticUrl = computed(() => staticFailed.value ? null : storedUrl.value)
const displayedUrl = computed(() => imageUrl.value || (removing.value ? null : staticUrl.value))
const error = ref<string | null>(null)

function handleImageError(): void {
  if (!imageUrl.value) staticFailed.value = true
  else releaseImage()
  error.value = t('product.imageLoadError')
}
function releaseImage(): void {
  if (imageUrl.value) URL.revokeObjectURL(imageUrl.value)
  imageUrl.value = null
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
  releaseImage()
  imageUrl.value = URL.createObjectURL(file)
  selectedFile.value = file
  removing.value = false
  error.value = null
  emit('dirty', true)
}

function removeImage(): void {
  releaseImage()
  selectedFile.value = null
  // Removing an unsaved selection is not a server-side DELETE. Only an
  // already persisted image should schedule a deletion on Product save.
  removing.value = Boolean(props.productId && hasStoredImage.value)
  error.value = null
  emit('dirty', removing.value)
}

/** Called only after the enclosing Product JSON CREATE/PATCH succeeded. */
async function commit(productId: string): Promise<Product | null> {
  if (!selectedFile.value && !removing.value) return null
  error.value = null
  if (!props.productId) adoptedProductId = productId
  try {
    const updated = selectedFile.value
      ? await uploadProductImage(productId, selectedFile.value)
      : await deleteProductImage(productId)
    selectedFile.value = null
    removing.value = false
    emit('dirty', false)
    // Parent installs the Product returned by this mutation (including image_url).
    return updated
  } catch (caught) {
    error.value = t('product.imageSaveError')
    throw caught
  }
}

// Switching records discards unsaved local files. Metadata refreshes for
// the SAME record must not clear a file awaiting upload after Product JSON save.
watch(
  () => props.productId,
  (next, previous) => {
    if (previous === undefined && next && next === adoptedProductId) {
      adoptedProductId = null
      return
    }
    adoptedProductId = null
    selectedFile.value = null
    removing.value = false
    staticFailed.value = false
    error.value = null
    emit('dirty', false)
    releaseImage()
  },
  { immediate: true },
)
watch(
  () => [props.imageAvailable, props.imagePath] as const,
  () => {
    staticFailed.value = false
    // After a successful upload, the parent supplies a new immutable URL.
    // Keep the local preview (or failed upload) until it actually finishes.
    if (!selectedFile.value && !removing.value) releaseImage()
  },
)
onBeforeUnmount(releaseImage)
defineExpose({ commit })
</script>

<template>
  <div class="product-image-field">
    <div class="product-image-preview" :aria-label="t('product.image')">
      <img v-if="displayedUrl && !removing" :src="displayedUrl" @error="handleImageError" :alt="t('product.image')" />
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
          v-if="displayedUrl || selectedFile || (hasStoredImage && !removing)"
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
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-bg-subtle);
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
  color: var(--ds-text-secondary);
  font-size: 12px;
  padding: 12px;
  text-align: center;
}
.product-image-controls {
  min-width: 200px;
  flex: 1;
}
.product-image-controls strong {
  color: var(--ds-text-primary);
}
.product-image-hint {
  margin: 6px 0;
  color: var(--ds-text-secondary);
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
  min-height: var(--ds-button-height-md);
  padding: 6px 10px;
  background: var(--ds-bg-surface);
  border: 1px solid var(--ds-border);
  border-radius: var(--ds-radius-sm);
  font: inherit;
  font-size: 13px;
  color: var(--ds-primary);
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
  outline: 2px solid var(--ds-primary);
  outline-offset: 2px;
}
.image-upload-button.disabled,
.image-remove-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.image-remove-button {
  color: var(--ds-danger);
}
.product-image-error {
  color: var(--ds-danger);
  font-size: 12px;
}
</style>
