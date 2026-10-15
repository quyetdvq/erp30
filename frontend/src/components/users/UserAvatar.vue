<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { readUserAvatar } from '@/services/userAvatar'

const props = withDefaults(defineProps<{
  userId: string | number
  hasAvatar?: boolean
  fallback: string
  revision?: number
  size?: 'small' | 'large'
}>(), { hasAvatar: false, revision: 0, size: 'small' })

const auth = useAuthStore()
const source = ref<string | null>(null)
const label = computed(() => props.fallback.trim() || 'User')
const initials = computed(() => Array.from(label.value)[0]?.toLocaleUpperCase() ?? 'U')
let requestVersion = 0
function release(): void {
  if (source.value) URL.revokeObjectURL(source.value)
  source.value = null
}
watch(
  () => [props.userId, props.hasAvatar, props.revision, auth.user?.id],
  () => {
    const version = ++requestVersion
    const generation = auth.getSessionGeneration()
    release()
    if (!props.hasAvatar || !auth.user) return
    void readUserAvatar(props.userId).then((blob) => {
      if (requestVersion !== version || generation !== auth.getSessionGeneration() || !auth.user) return
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(blob.type)) return
      source.value = URL.createObjectURL(blob)
    }).catch(() => {
      // A 404, 403 or logout must not disclose private paths; initials remain visible.
    })
  },
  { immediate: true },
)
onBeforeUnmount(() => { ++requestVersion; release() })
</script>

<template>
  <span class="user-avatar" :class="`user-avatar--${size}`">
    <img v-if="source" :src="source" :alt="label" class="user-avatar__image" />
    <span v-else role="img" :aria-label="label">{{ initials }}</span>
  </span>
</template>

<style scoped>
.user-avatar { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 50%; width: 32px; height: 32px; overflow: hidden; background: #e7f1f6; color: #24536b; font-weight: 700; }
.user-avatar--large { width: 82px; height: 82px; font-size: 28px; }
.user-avatar__image { width: 100%; height: 100%; object-fit: cover; display: block; }
</style>
