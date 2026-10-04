<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const email = ref('')
const password = ref('')
const remember = ref(true)

async function submit(): Promise<void> {
  try {
    await auth.login(email.value, password.value, remember.value)
    void router.push('/')
  } catch {
    // Store exposes the API error next to the form.
  }
}
</script>

<template>
  <main class="auth-screen">
    <section class="auth-card">
      <div class="auth-brand"><span class="brand-mark">D</span><span class="brand-copy"><strong>Dinktech</strong><small>ERP Workspace</small></span></div>
      <h1>Đăng nhập workspace</h1>
      <p class="lead">Đăng nhập để làm việc với dữ liệu doanh nghiệp của bạn.</p>
      <form @submit.prevent="submit">
        <div class="form-row"><label for="login-email">Email hoặc tên tài khoản</label><input id="login-email" v-model="email" class="field-input" autocomplete="username" placeholder="you@company.com" required /></div>
        <div class="form-row"><label for="login-password">Mật khẩu</label><input id="login-password" v-model="password" class="field-input" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu" required /></div>
        <label class="check-line"><input v-model="remember" type="checkbox" /> Ghi nhớ phiên đăng nhập</label>
        <p v-if="auth.error" class="form-error" role="alert">{{ auth.error }}</p>
        <button class="primary-button full-button" type="submit" :disabled="auth.busy">{{ auth.busy ? 'Đang đăng nhập...' : 'Đăng nhập' }}</button>
      </form>
    </section>
  </main>
</template>

<style scoped>
.check-line { display: flex; align-items: center; gap: 8px; margin: 1px 0 18px; color: #6e8295; font-size: 12px; }
</style>
