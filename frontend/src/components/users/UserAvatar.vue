<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { uploadImageUrl } from '@/services/uploadImageUrl'

const props = withDefaults(defineProps<{
  userId: string | number
  hasAvatar?: boolean
  avatarUrl?: string | null
  fallback: string
  revision?: number
  size?: 'small' | 'large'
}>(), { hasAvatar: false, revision: 0, size: 'small' })

const auth = useAuthStore()
const failed = ref(false)
const imageUrl = computed(() =>
  auth.user && props.hasAvatar && !failed.value
    ? uploadImageUrl(props.avatarUrl, 'avatar')
    : null,
)
const label = computed(() => props.fallback.trim() || 'User')
const initials = computed(() => Array.from(label.value)[0]?.toLocaleUpperCase() ?? 'U')

watch(
  () => [props.userId, props.hasAvatar, props.avatarUrl, props.revision, auth.user?.id],
  () => { failed.value = false },
)
</script>

<template>
  <span class="user-avatar" :class="`user-avatar--${size}`">
    <img v-if="imageUrl" :src="imageUrl" @error="failed = true" :alt="label" class="user-avatar__image" />
    <span v-else role="img" :aria-label="label">{{ initials }}</span>
  </span>
</template>

<style scoped>
.user-avatar { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 50%; width: 32px; height: 32px; overflow: hidden; background: #e7f1f6; color: #24536b; font-weight: 700; }
.user-avatar--large { width: 82px; height: 82px; font-size: 28px; }
.user-avatar__image { width: 100%; height: 100%; object-fit: cover; display: block; }
</style>
