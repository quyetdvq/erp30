<script setup lang="ts">
import { computed, ref } from 'vue'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const username = ref(auth.user?.username || '')
const email = ref(auth.user?.email || '')
const password = ref('')
const saved = ref(false)
const error = ref('')
const unsaved = useUnsavedChanges(
  computed(() => ({
    username: username.value,
    email: email.value,
    password: password.value,
  })),
)

async function save(): Promise<void> {
  saved.value = false
  error.value = ''
  try {
    await auth.saveProfile({
      username: username.value,
      email: email.value,
      password: password.value || undefined,
    })
    password.value = ''
    unsaved.markClean()
    saved.value = true
  } catch {
    error.value = 'Không thể lưu profile. Vui lòng kiểm tra lại thông tin.'
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
      <div class="avatar-large">{{ (auth.user?.username || 'U').slice(0, 1).toUpperCase() }}</div>
      <strong>{{ auth.user?.username }}</strong
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
          <button class="primary-button" type="submit">Lưu thay đổi</button>
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
