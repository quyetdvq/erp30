<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { setLocale, type AppLocale } from '@/i18n'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const { t, locale } = useI18n()
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

function toggleLocale(): void {
  setLocale((locale.value === 'vi' ? 'en' : 'vi') as AppLocale)
}
</script>

<template>
  <main class="auth-screen">
    <section class="auth-card">
      <button
        class="language-button auth-language"
        type="button"
        :aria-label="t('shell.language')"
        @click="toggleLocale"
      >
        {{ locale === 'vi' ? 'Tiếng Việt' : 'English' }}⌄
      </button>
      <div class="auth-brand">
        <span class="brand-mark">D</span
        ><span class="brand-copy"><strong>Dinktech</strong><small>ERP Workspace</small></span>
      </div>
      <h1>{{ t('auth.title') }}</h1>
      <p class="lead">{{ t('auth.lead') }}</p>
      <form @submit.prevent="submit">
        <div class="form-row">
          <label for="login-email">{{ t('auth.identity') }}</label
          ><input
            id="login-email"
            v-model="email"
            class="field-input"
            autocomplete="username"
            placeholder="you@company.com"
            required
          />
        </div>
        <div class="form-row">
          <label for="login-password">{{ t('auth.password') }}</label
          ><input
            id="login-password"
            v-model="password"
            class="field-input"
            type="password"
            autocomplete="current-password"
            :placeholder="t('auth.passwordPlaceholder')"
            required
          />
        </div>
        <label class="check-line"
          ><input v-model="remember" type="checkbox" /> {{ t('auth.remember') }}</label
        >
        <p v-if="auth.error" class="form-error" role="alert">{{ auth.error }}</p>
        <button class="primary-button full-button" type="submit" :disabled="auth.busy">
          {{ auth.busy ? t('auth.submitting') : t('auth.submit') }}
        </button>
      </form>
    </section>
  </main>
</template>

<style scoped>
.auth-language {
  float: right;
}
.check-line {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 1px 0 18px;
  color: #4c6273;
  font-size: 12px;
}
</style>
