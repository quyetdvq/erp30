<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { uploadImageUrl } from '@/services/uploadImageUrl'

const props = withDefaults(
  defineProps<{
    productName: string
    imageAvailable?: boolean
    imagePath?: string | null
  }>(),
  { imageAvailable: false },
)
const { t } = useI18n()
const auth = useAuthStore()
const host = ref<HTMLElement | null>(null)
const visible = ref(false)
const failed = ref(false)
const imageUrl = computed(() =>
  visible.value && auth.user && !failed.value
    ? uploadImageUrl(props.imagePath, 'product')
    : null,
)
let observer: IntersectionObserver | null = null

watch(
  [() => props.imagePath, () => props.imageAvailable, () => auth.user?.id],
  () => { failed.value = false },
)

onMounted(() => {
  if (typeof IntersectionObserver === 'undefined') {
    visible.value = true
    return
  }
  observer = new IntersectionObserver((entries) => {
    if (!entries.some((entry) => entry.isIntersecting)) return
    visible.value = true
    observer?.disconnect()
    observer = null
  }, { rootMargin: '160px' })
  if (host.value) observer.observe(host.value)
})
onBeforeUnmount(() => { observer?.disconnect() })
</script>

<template>
  <div
    ref="host"
    class="product-list-thumbnail"
    role="img"
    :aria-label="imageUrl ? productName : failed ? t('product.imageLoadError') : imageAvailable ? t('product.image') : t('product.imageEmpty')"
    :title="failed ? t('product.imageLoadError') : imageUrl || imageAvailable ? productName : t('product.imageEmpty')"
  >
    <img
      v-if="imageUrl"
      :src="imageUrl"
      @error="failed = true"
      alt=""
      loading="lazy"
      decoding="async"
    />
    <svg v-else viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.5" />
      <circle cx="8.5" cy="9" r="1.5" fill="currentColor" />
      <path d="m4 17 5-5 4 4 3-3 4 4" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
    </svg>
  </div>
</template>

<style scoped>
.product-list-thumbnail {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  overflow: hidden;
  border: 1px solid #dbe5ea;
  border-radius: 6px;
  background: #f5f8fa;
  color: #a8bbc7;
}
.product-list-thumbnail img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.product-list-thumbnail svg {
  width: 24px;
  height: 24px;
}
</style>
