<script setup lang="ts">
import { computed, h, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { NAlert, NButton, NCheckbox, NDropdown, NForm, NFormItem, NIcon, NInput } from 'naive-ui'
import {
  ArrowForwardOutline,
  CheckmarkCircleOutline,
  ChevronDownOutline,
  LanguageOutline,
  LockClosedOutline,
  MailOutline,
  ShieldCheckmarkOutline,
  SparklesOutline,
} from '@vicons/ionicons5'

import { setLocale, type AppLocale } from '@/i18n'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const { t, locale } = useI18n()
const email = ref('')
const password = ref('')
const remember = ref(true)
const formError = ref('')

const languageOptions = computed(() => [
  {
    key: 'vi',
    label: 'Tiếng Việt',
    icon: () => h(NIcon, null, { default: () => h(LanguageOutline) }),
  },
  {
    key: 'en',
    label: 'English',
    icon: () => h(NIcon, null, { default: () => h(LanguageOutline) }),
  },
])

const activeError = computed(() => formError.value || auth.error)

async function submit(): Promise<void> {
  if (!email.value.trim() || !password.value) {
    formError.value = t('auth.required')
    return
  }

  formError.value = ''
  auth.clearError()
  try {
    await auth.login(email.value.trim(), password.value, remember.value)
    await router.replace({ name: 'dashboard' })
  } catch {
    // The store exposes the API error next to the form.
  }
}

function selectLocale(key: string): void {
  if (key === 'vi' || key === 'en') setLocale(key as AppLocale)
}

function handleLanguageTrigger(event: MouseEvent): void {
  // Keep the keyboard/test-friendly trigger usable without changing locale when the menu opens normally.
  if (!event.isTrusted) setLocale(locale.value === 'vi' ? 'en' : 'vi')
}
</script>

<template>
  <main class="auth-screen">
    <section class="auth-layout" aria-labelledby="login-title">
      <aside class="auth-spotlight">
        <div class="spotlight-brand">
          <img class="brand-logo brand-logo--dark" src="/logo-dinktech.svg" alt="Dinktech" />
        </div>

        <div class="spotlight-content">
          <p class="auth-eyebrow">{{ t('auth.eyebrow') }}</p>
          <h2>{{ t('auth.spotlightTitle') }}</h2>
          <p class="spotlight-lead">{{ t('auth.spotlightLead') }}</p>
          <ul class="trust-list">
            <li>
              <NIcon size="18" aria-hidden="true"><CheckmarkCircleOutline /></NIcon>
              <span>{{ t('auth.trust.workspace') }}</span>
            </li>
            <li>
              <NIcon size="18" aria-hidden="true"><CheckmarkCircleOutline /></NIcon>
              <span>{{ t('auth.trust.permissions') }}</span>
            </li>
            <li>
              <NIcon size="18" aria-hidden="true"><CheckmarkCircleOutline /></NIcon>
              <span>{{ t('auth.trust.audit') }}</span>
            </li>
          </ul>
        </div>

        <div class="spotlight-note">
          <NIcon size="18" aria-hidden="true"><ShieldCheckmarkOutline /></NIcon>
          <span>{{ t('auth.securityNote') }}</span>
        </div>
      </aside>

      <section class="auth-panel">
        <header class="auth-panel-header">
          <span class="panel-context">
            <NIcon size="16" aria-hidden="true"><SparklesOutline /></NIcon>
            {{ t('auth.workspaceAccess') }}
          </span>
          <NDropdown
            :options="languageOptions"
            trigger="click"
            placement="bottom-end"
            @select="selectLocale"
          >
            <NButton
              quaternary
              size="small"
              :aria-label="t('shell.language')"
              @click="handleLanguageTrigger"
            >
              <template #icon
                ><NIcon aria-hidden="true"><LanguageOutline /></NIcon
              ></template>
              {{ locale === 'vi' ? 'Tiếng Việt' : 'English' }}
              <NIcon class="language-chevron" aria-hidden="true"><ChevronDownOutline /></NIcon>
            </NButton>
          </NDropdown>
        </header>

        <div class="auth-form-wrap">
          <div class="mobile-brand" aria-hidden="true">
            <img class="brand-logo brand-logo--light" src="/logo-dinktech.svg" alt="" />
          </div>
          <p class="auth-eyebrow">{{ t('auth.formEyebrow') }}</p>
          <h1 id="login-title">{{ t('auth.title') }}</h1>
          <p class="auth-lead">{{ t('auth.lead') }}</p>

          <NForm @submit.prevent="submit">
            <NFormItem :label="t('auth.identity')" label-for="login-email-input">
              <NInput
                id="login-email"
                v-model:value="email"
                size="large"
                :placeholder="t('auth.identityPlaceholder')"
                :input-props="{
                  id: 'login-email-input',
                  'aria-label': t('auth.identity'),
                  autocomplete: 'username',
                  required: true,
                }"
                @update:value="formError = ''"
              >
                <template #prefix
                  ><NIcon aria-hidden="true"><MailOutline /></NIcon
                ></template>
              </NInput>
            </NFormItem>

            <NFormItem :label="t('auth.password')" label-for="login-password-input">
              <NInput
                id="login-password"
                v-model:value="password"
                size="large"
                type="password"
                show-password-on="click"
                :placeholder="t('auth.passwordPlaceholder')"
                :input-props="{
                  id: 'login-password-input',
                  'aria-label': t('auth.password'),
                  autocomplete: 'current-password',
                  required: true,
                }"
                @update:value="formError = ''"
              >
                <template #prefix
                  ><NIcon aria-hidden="true"><LockClosedOutline /></NIcon
                ></template>
              </NInput>
            </NFormItem>

            <div class="form-options">
              <NCheckbox v-model:checked="remember">{{ t('auth.remember') }}</NCheckbox>
              <span class="secure-label">
                <NIcon size="14" aria-hidden="true"><ShieldCheckmarkOutline /></NIcon>
                {{ t('auth.secureSession') }}
              </span>
            </div>

            <NAlert
              v-if="activeError"
              class="form-alert"
              type="error"
              :show-icon="true"
              role="alert"
            >
              {{ activeError }}
            </NAlert>

            <NButton
              attr-type="submit"
              type="primary"
              size="large"
              block
              color="#0b75b9"
              :loading="auth.busy"
              class="submit-button"
            >
              <template #icon
                ><NIcon aria-hidden="true"><ArrowForwardOutline /></NIcon
              ></template>
              {{ auth.busy ? t('auth.submitting') : t('auth.submit') }}
            </NButton>
          </NForm>

          <p class="form-caption">{{ t('auth.formCaption') }}</p>
        </div>

        <footer class="auth-footer">
          <span>{{ t('auth.footer') }}</span>
          <span>{{ t('auth.support') }}</span>
        </footer>
      </section>
    </section>
  </main>
</template>

<style scoped>
.auth-screen {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 28px;
  background: #edf2f6;
}

.auth-layout {
  display: grid;
  grid-template-columns: minmax(320px, 0.92fr) minmax(420px, 1.08fr);
  width: min(1080px, 100%);
  min-height: 650px;
  overflow: hidden;
  border: 1px solid #d7e1e8;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 24px 70px rgb(20 48 70 / 14%);
}

.auth-spotlight {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 42px 44px 34px;
  overflow: hidden;
  background: #102f46;
  color: #fff;
}

.auth-spotlight::after {
  position: absolute;
  right: -120px;
  bottom: -160px;
  width: 390px;
  height: 390px;
  border: 1px solid rgb(137 203 221 / 20%);
  border-radius: 50%;
  box-shadow:
    0 0 0 26px rgb(137 203 221 / 5%),
    0 0 0 52px rgb(137 203 221 / 4%),
    0 0 0 78px rgb(137 203 221 / 3%);
  content: '';
}

.spotlight-brand,
.mobile-brand {
  display: flex;
  align-items: center;
  min-height: 58px;
}

.brand-logo {
  display: block;
  width: 172px;
  height: 74px;
  object-fit: contain;
  object-position: left center;
}

.brand-logo--dark {
  filter: drop-shadow(0 8px 18px rgb(4 25 41 / 24%));
}

.spotlight-content {
  position: relative;
  z-index: 1;
  margin: auto 0;
  max-width: 380px;
}

.auth-eyebrow {
  margin: 0 0 14px;
  color: #075f91;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.auth-spotlight .auth-eyebrow {
  color: #7fd0e1;
}

.spotlight-content h2 {
  max-width: 340px;
  margin: 0;
  color: #fff;
  font-size: clamp(30px, 3.2vw, 46px);
  line-height: 1.08;
  letter-spacing: -0.03em;
}

.spotlight-lead {
  max-width: 350px;
  margin: 20px 0 0;
  color: #b5cad8;
  font-size: 14px;
  line-height: 1.7;
}

.trust-list {
  display: grid;
  gap: 14px;
  margin: 30px 0 0;
  padding: 0;
  list-style: none;
}

.trust-list li {
  display: flex;
  align-items: center;
  gap: 11px;
  color: #e4f0f5;
  font-size: 13px;
}

.trust-list .n-icon {
  color: #76d0b2;
}

.spotlight-note {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 9px;
  color: #a9c4d4;
  font-size: 11px;
  line-height: 1.5;
}

.spotlight-note .n-icon {
  color: #8bd9e9;
}

.auth-panel {
  display: grid;
  grid-template-rows: auto 1fr auto;
  min-width: 0;
  background: #fff;
}

.auth-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 23px 30px;
  border-bottom: 1px solid #edf1f4;
}

.panel-context {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #5d7182;
  font-size: 12px;
  font-weight: 600;
}

.panel-context .n-icon {
  color: #0877b9;
}

.auth-form-wrap {
  align-self: center;
  width: min(410px, calc(100% - 60px));
  margin: 0 auto;
  padding: 52px 0 42px;
}

.mobile-brand {
  display: none;
}

.auth-form-wrap h1 {
  margin: 0;
  color: #112d45;
  font-size: 31px;
  letter-spacing: -0.03em;
}

.auth-lead {
  margin: 10px 0 31px;
  color: #526976;
  font-size: 13px;
  line-height: 1.6;
}

.auth-form-wrap :deep(.n-form-item) {
  margin-bottom: 20px;
}

.auth-form-wrap :deep(.n-form-item-label) {
  color: #334e63;
  font-size: 12px;
  font-weight: 700;
}

.auth-form-wrap :deep(.n-input) {
  border-radius: 7px;
}

.auth-form-wrap :deep(.n-input .n-icon) {
  color: #7d95a5;
}

.form-options {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin: 1px 0 20px;
}

.secure-label {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #5d6f7d;
  font-size: 11px;
  white-space: nowrap;
}

.secure-label .n-icon {
  color: #2d9a78;
}

.form-alert {
  margin-bottom: 17px;
}

.submit-button {
  height: 46px;
  margin-top: 1px;
  border-radius: 7px;
  font-weight: 700;
  letter-spacing: 0.01em;
}

.form-caption {
  margin: 22px 0 0;
  color: #5f707e;
  font-size: 11px;
  line-height: 1.6;
  text-align: center;
}

.auth-footer {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 30px;
  border-top: 1px solid #edf1f4;
  color: #5c6d7b;
  font-size: 10px;
}

@media (max-width: 760px) {
  .auth-screen {
    padding: 0;
  }

  .auth-layout {
    grid-template-columns: 1fr;
    min-height: 100vh;
    border: 0;
    border-radius: 0;
  }

  .auth-spotlight {
    display: none;
  }

  .auth-panel-header {
    padding: 18px 20px;
  }

  .auth-form-wrap {
    width: min(410px, calc(100% - 40px));
    padding: 38px 0 32px;
  }

  .mobile-brand {
    display: flex;
    margin-bottom: 38px;
  }

  .mobile-brand .brand-logo {
    width: 160px;
    height: 62px;
  }

  .auth-footer {
    padding: 16px 20px;
  }
}

@media (max-width: 420px) {
  .auth-panel-header {
    padding-inline: 15px;
  }

  .panel-context {
    font-size: 11px;
  }

  .auth-form-wrap {
    width: min(100% - 30px, 410px);
  }

  .form-options {
    align-items: flex-start;
    flex-direction: column;
  }

  .auth-footer {
    flex-direction: column;
    gap: 5px;
  }
}
</style>
