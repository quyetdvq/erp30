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
  <div class="page-heading profile-page-heading">
    <div>
      <span class="profile-page-heading__eyebrow">TÀI KHOẢN CÁ NHÂN</span>
      <h1>Hồ sơ cá nhân</h1>
      <p>Quản lý thông tin tài khoản, ảnh đại diện và bảo mật đăng nhập.</p>
    </div>
  </div>

  <div class="profile-workspace">
    <div class="profile-layout">
      <aside class="content-panel profile-summary" aria-label="Tổng quan hồ sơ">
        <header class="profile-summary__header">
          <h2>Tổng quan tài khoản</h2>
          <p>Ảnh đại diện và quyền truy cập của bạn</p>
        </header>
        <div class="profile-summary__body">
          <UserAvatarManager
            v-if="auth.user"
            :key="String(auth.user.id)"
            :user-id="auth.user.id"
            :has-avatar="auth.user.has_avatar"
            :avatar-url="auth.user.avatar_url"
            :label="auth.user.name || auth.user.username"
            :can-edit="true"
            @updated="void avatarUpdated()"
          >
            <template #identity>
              <div class="profile-identity">
                <strong class="profile-identity__name">{{ auth.user?.name || auth.user?.username }}</strong>
                <span class="profile-identity__username">@{{ auth.user?.username }}</span>
                <span v-if="auth.user?.email" class="profile-identity__email">{{ auth.user?.email }}</span>
              </div>
            </template>
          </UserAvatarManager>
        </div>
        <section class="profile-summary__access" aria-labelledby="profile-groups-heading">
          <div class="profile-summary__access-heading">
            <h3 id="profile-groups-heading">Nhóm quyền</h3>
            <span>{{ auth.user?.groups?.length || 0 }} nhóm</span>
          </div>
          <div v-if="auth.user?.groups?.length" class="profile-tags">
            <span v-for="group in auth.user?.groups || []" :key="group" class="tag">{{ group }}</span>
          </div>
          <p v-else class="profile-summary__empty">Chưa có nhóm quyền được gán.</p>
        </section>
      </aside>

      <section class="content-panel form-panel" aria-label="Chỉnh sửa hồ sơ cá nhân">
        <form class="profile-form" @submit.prevent="save">
          <section class="profile-form__section" aria-labelledby="profile-account-heading">
            <div class="profile-form__section-heading">
              <span class="profile-form__step" aria-hidden="true">01</span>
              <div>
                <h2 id="profile-account-heading">Thông tin tài khoản</h2>
                <p>Thông tin dùng để nhận diện và liên hệ trong hệ thống.</p>
              </div>
            </div>
            <div class="profile-form__grid">
              <div class="form-row">
                <label for="profile-username">Tên tài khoản</label>
                <input id="profile-username" v-model="username" class="field-input" autocomplete="username" required />
              </div>
              <div class="form-row">
                <label for="profile-fullname">Họ và tên</label>
                <input
                  id="profile-fullname"
                  v-model="name"
                  class="field-input"
                  autocomplete="name"
                  :aria-invalid="Boolean(nameError)"
                  :aria-describedby="nameError ? 'profile-name-error' : undefined"
                  @input="nameError = ''"
                />
                <small v-if="nameError" id="profile-name-error" role="alert" class="form-error">{{ nameError }}</small>
              </div>
              <div class="form-row profile-form__wide">
                <label for="profile-email">Email</label>
                <input id="profile-email" v-model="email" class="field-input" type="email" autocomplete="email" required />
              </div>
            </div>
          </section>

          <section class="profile-form__section" aria-labelledby="profile-security-heading">
            <div class="profile-form__section-heading">
              <span class="profile-form__step" aria-hidden="true">02</span>
              <div>
                <h2 id="profile-security-heading">Bảo mật đăng nhập</h2>
                <p>Chỉ nhập mật khẩu mới khi bạn muốn thay đổi mật khẩu hiện tại.</p>
              </div>
            </div>
            <div class="profile-form__grid">
              <div class="form-row profile-form__wide">
                <label for="profile-password">Mật khẩu mới <span class="profile-form__optional">(không bắt buộc)</span></label>
                <input
                  id="profile-password"
                  v-model="password"
                  class="field-input"
                  type="password"
                  autocomplete="new-password"
                  minlength="8"
                  aria-describedby="profile-password-help"
                />
                <small id="profile-password-help" class="profile-form__help">Để trống nếu không đổi mật khẩu. Nếu thay đổi, hãy nhập ít nhất 8 ký tự.</small>
              </div>
            </div>
          </section>

          <footer class="profile-form__footer">
            <div class="profile-form__feedback" aria-live="polite">
              <p v-if="saved" class="form-success" role="status">Đã cập nhật profile.</p>
              <p v-else-if="error" class="form-error" role="alert">{{ error }}</p>
              <p v-else class="profile-form__hint">Các thay đổi sẽ được áp dụng sau khi lưu.</p>
            </div>
            <div class="profile-form__actions">
              <span v-if="unsaved.dirty" class="profile-form__unsaved">Chưa lưu thay đổi</span>
              <button data-ds-icon="save" class="primary-button" type="submit" :disabled="busy">
                {{ busy ? 'Đang lưu...' : 'Lưu thay đổi' }}
              </button>
            </div>
          </footer>
        </form>
      </section>
    </div>
  </div>
</template>

<style scoped>
/* Profile owns composition only; forms.css owns every input, validation and button skin. */
.profile-page-heading__eyebrow {
  display: inline-block;
  margin-bottom: var(--ds-space-2);
  color: var(--ds-primary);
  font-size: var(--ds-font-xs);
  font-weight: 700;
  letter-spacing: .09em;
}
.profile-workspace {
  width: min(100%, 1680px);
  margin-inline: auto;
  container-type: inline-size;
}
.profile-layout {
  display: grid;
  grid-template-columns: minmax(270px, 316px) minmax(0, 1fr);
  align-items: start;
  gap: var(--ds-space-5);
  min-width: 0;
}
.profile-summary,
.form-panel { min-width: 0; overflow: hidden; }
.profile-summary__header {
  padding: var(--ds-space-5) var(--ds-space-5) var(--ds-space-4);
  border-bottom: 1px solid var(--ds-border-subtle);
}
.profile-summary__header h2,
.profile-summary__access h3,
.profile-form__section-heading h2 {
  margin: 0;
  color: var(--ds-text-primary);
  font-size: var(--ds-font-lg);
  font-weight: 700;
  letter-spacing: -.01em;
}
.profile-summary__header p,
.profile-form__section-heading p {
  margin: var(--ds-space-1) 0 0;
  color: var(--ds-text-muted);
  font-size: var(--ds-font-sm);
  line-height: 1.6;
}
.profile-summary__body { padding: var(--ds-space-6) var(--ds-space-5); }
.profile-summary :deep(.avatar-manager) {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--ds-space-4);
  min-width: 0;
}
.profile-summary :deep(.avatar-manager__preview) {
  width: 96px;
  height: 96px;
  border: 4px solid var(--ds-bg-surface);
  box-shadow: 0 0 0 1px var(--ds-border-subtle), var(--ds-shadow-card);
}
.profile-summary :deep(.avatar-manager__preview .user-avatar),
.profile-summary :deep(.avatar-manager__local) { width: 100%; height: 100%; }
.profile-identity {
  display: grid;
  justify-items: center;
  gap: var(--ds-space-1);
  width: 100%;
  min-width: 0;
  text-align: center;
}
.profile-identity__name {
  color: var(--ds-text-primary);
  font-size: var(--ds-font-xl);
  font-weight: 700;
  line-height: 1.35;
  overflow-wrap: anywhere;
}
.profile-identity__username {
  color: var(--ds-text-secondary);
  font-size: var(--ds-font-sm);
  overflow-wrap: anywhere;
}
.profile-identity__email {
  color: var(--ds-text-muted);
  font-size: var(--ds-font-sm);
  overflow-wrap: anywhere;
}
.profile-summary :deep(.avatar-manager__actions) {
  width: 100%;
  min-width: 0;
  padding-top: var(--ds-space-4);
  border-top: 1px solid var(--ds-border-subtle);
}
.profile-summary :deep(.avatar-manager__file) {
  min-width: 0;
  color: var(--ds-control-label-color);
  font-size: var(--ds-control-label-size);
  font-weight: var(--ds-control-label-weight);
}
.profile-summary :deep(.avatar-manager__buttons) { display: flex; flex-wrap: wrap; gap: var(--ds-space-2); }
.profile-summary :deep(.avatar-manager__buttons > button) { flex: 1 1 105px; }
.profile-summary :deep(.security-note) {
  margin: 0;
  color: var(--ds-text-muted);
  font-size: var(--ds-font-xs);
  line-height: 1.5;
}
.profile-summary__access {
  padding: var(--ds-space-4) var(--ds-space-5) var(--ds-space-5);
  border-top: 1px solid var(--ds-border-subtle);
  background: var(--ds-bg-subtle);
}
.profile-summary__access-heading { display: flex; align-items: baseline; justify-content: space-between; gap: var(--ds-space-2); }
.profile-summary__access h3 { font-size: var(--ds-font-md); }
.profile-summary__access-heading > span { color: var(--ds-text-muted); font-size: var(--ds-font-xs); white-space: nowrap; }
.profile-tags { display: flex; flex-wrap: wrap; gap: var(--ds-space-2); margin-top: var(--ds-space-3); }
.profile-tags .tag { margin: 0; overflow-wrap: anywhere; }
.profile-summary__empty { margin: var(--ds-space-3) 0 0; color: var(--ds-text-muted); font-size: var(--ds-font-sm); }
.profile-form { display: grid; }
.profile-form__section { padding: var(--ds-space-6); min-width: 0; }
.profile-form__section + .profile-form__section { border-top: 1px solid var(--ds-border-subtle); }
.profile-form__section-heading { display: flex; align-items: flex-start; gap: var(--ds-space-3); margin-bottom: var(--ds-space-6); }
.profile-form__section-heading > div { min-width: 0; }
.profile-form__step {
  display: inline-grid;
  place-items: center;
  flex: 0 0 34px;
  height: 34px;
  border-radius: var(--ds-radius-md);
  background: var(--ds-primary-soft);
  color: var(--ds-primary);
  font-size: var(--ds-font-xs);
  font-weight: 700;
}
.profile-form__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 var(--ds-space-5); }
.profile-form__grid > .form-row { min-width: 0; }
.profile-form__wide { grid-column: 1 / -1; }
.profile-form__optional { color: var(--ds-text-muted); font-weight: 400; }
.profile-form__help { display: block; color: var(--ds-text-muted); font-size: var(--ds-font-xs); line-height: 1.5; }
.profile-form__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--ds-space-3);
  min-width: 0;
  padding: var(--ds-space-4) var(--ds-space-6);
  border-top: 1px solid var(--ds-border-subtle);
  background: var(--ds-bg-subtle);
}
.profile-form__feedback { flex: 1 1 210px; min-width: 0; }
.profile-form__feedback p { margin: 0; }
.form-success { color: var(--ds-success); font-size: var(--ds-font-sm); font-weight: 600; }
.profile-form__hint { color: var(--ds-text-muted); font-size: var(--ds-font-xs); }
.profile-form__actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: var(--ds-space-3); }
.profile-form__unsaved { color: var(--ds-warning); font-size: var(--ds-font-xs); font-weight: 600; }
@container (max-width: 950px) {
  .profile-layout { grid-template-columns: minmax(0, 1fr); }
  .profile-summary :deep(.avatar-manager) { max-width: 480px; margin-inline: auto; }
}
/* When the workspace narrows, show identity and avatar actions in one compact row. */
@container (min-width: 700px) and (max-width: 950px) {
  .profile-summary :deep(.avatar-manager) {
    display: grid;
    grid-template-columns: 96px minmax(0, 1fr) minmax(210px, 1.2fr);
    align-items: center;
    max-width: none;
    margin: 0;
  }
  .profile-identity { justify-items: start; text-align: left; }
  .profile-summary :deep(.avatar-manager__actions) {
    padding-top: 0;
    padding-left: var(--ds-space-4);
    border-top: 0;
    border-left: 1px solid var(--ds-border-subtle);
  }
}
@container (max-width: 360px) {
  .profile-summary :deep(.avatar-manager__buttons) { flex-direction: column; }
  .profile-summary :deep(.avatar-manager__buttons > button) { flex: 0 0 auto; width: 100%; }
}
@container (max-width: 640px) {
  .profile-layout { gap: var(--ds-space-4); }
  .profile-form__section { padding: var(--ds-space-4); }
  .profile-form__grid { grid-template-columns: minmax(0, 1fr); }
  .profile-form__wide { grid-column: auto; }
  .profile-form__section-heading { margin-bottom: var(--ds-space-4); }
  .profile-form__footer { padding: var(--ds-space-4); }
  .profile-form__feedback { flex-basis: 100%; }
  .profile-form__actions { width: 100%; }
  .profile-form__actions > button { flex: 1; }
}
</style>
