<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import { profileNamePatch } from '@/features/account/profile/profileFields'
import UserAvatarManager from '@/components/users/UserAvatarManager.vue'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const username = ref(auth.user?.username || '')
const email = ref(auth.user?.email || '')
const name = ref(auth.user?.name || '')
const nameError = ref('')
const busy = ref(false)
const password = ref('')
const saved = ref(false)
const error = ref('')
const unsaved = useUnsavedChanges(
  computed(() => ({
    username: username.value,
    name: name.value,
    email: email.value,
    password: password.value,
  })),
)

watch(() => auth.user?.id, () => {
  username.value = auth.user?.username || ''
  name.value = auth.user?.name || ''
  email.value = auth.user?.email || ''
  password.value = ''
  unsaved.markClean()
})
async function avatarUpdated(): Promise<void> {
  try {
    await auth.refreshSelfUser()
  } catch {
    error.value = 'Không thể làm mới thông tin Avatar. Hãy tải lại trang.'
  }
}
async function save(): Promise<void> {
  if (busy.value) return
  saved.value = false
  error.value = ''
  nameError.value = ''
  let displayPatch: { name?: string }
  try {
    displayPatch = profileNamePatch(auth.user?.name ?? null, name.value)
  } catch {
    nameError.value = 'Họ và tên không được để trống hoặc dài hơn 255 ký tự.'
    return
  }
  busy.value = true
  try {
    await auth.saveProfile({
      username: username.value,
      email: email.value,
      ...displayPatch,
      password: password.value || undefined,
    })
    password.value = ''
    unsaved.markClean()
    saved.value = true
  } catch {
    error.value = 'Không thể lưu profile. Vui lòng kiểm tra lại thông tin.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>Profile user</h1>
      <p>Thông tin cá nhân và bảo mật phiên đăng nhập.</p>
    </div>
  </div>
  <div class="profile-layout">
    <section class="content-panel profile-summary">
      <UserAvatarManager v-if="auth.user" :key="String(auth.user.id)" :user-id="auth.user.id"
        :has-avatar="auth.user.has_avatar" :label="auth.user.name || auth.user.username" :can-edit="true"
        @updated="void avatarUpdated()" />
      <strong>{{ auth.user?.name || auth.user?.username }}</strong
      ><small>{{ auth.user?.email }}</small>
      <div class="profile-tags">
        <span v-for="group in auth.user?.groups || []" :key="group" class="tag">{{ group }}</span>
      </div>
    </section>
    <section class="content-panel form-panel">
      <h2>Thông tin tài khoản</h2>
      <form @submit.prevent="save">
        <div class="form-row">
          <label for="profile-username">Tên tài khoản</label
          ><input id="profile-username" v-model="username" class="field-input" required />
        </div>
        <div class="form-row">
          <label for="profile-fullname">Họ và tên</label>
          <input id="profile-fullname" v-model="name" class="field-input" autocomplete="name"
            :aria-invalid="Boolean(nameError)" @input="nameError = ''" />
          <small v-if="nameError" role="alert" class="form-error">{{ nameError }}</small>
        </div>
        <div class="form-row">
          <label for="profile-email">Email</label
          ><input id="profile-email" v-model="email" class="field-input" type="email" required />
        </div>
        <div class="form-row">
          <label for="profile-password">Mật khẩu mới <small>(để trống nếu không đổi)</small></label
          ><input
            id="profile-password"
            v-model="password"
            class="field-input"
            type="password"
            minlength="8"
          />
        </div>
        <p v-if="saved" class="form-success">Đã cập nhật profile.</p>
        <p v-if="error" class="form-error">{{ error }}</p>
        <div class="form-actions">
          <button class="primary-button" type="submit" :disabled="busy">{{ busy ? 'Đang lưu...' : 'Lưu thay đổi' }}</button>
        </div>
      </form>
    </section>
  </div>
</template>

<style scoped>
.profile-tags {
  margin-top: 16px;
}
.form-panel small {
  color: #8091a0;
  font-weight: 400;
}
.form-success {
  color: #1c8a62;
  font-size: 12px;
}
</style>
