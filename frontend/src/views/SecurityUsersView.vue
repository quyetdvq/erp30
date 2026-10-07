<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { TableDetailModal, TableStatus } from '@/components/lists'
import type { TableDetailField } from '@/components/lists/TableDetailModal.vue'
import { getCurrentUser, type AuthUser } from '@/services/auth'
import { reportApiError } from '@/services/errorBoundary'

const { t } = useI18n()
const user = ref<AuthUser | null>(null)
const error = ref<unknown | null>(null)
const busy = ref(false)
const tab = ref<'overview' | 'groups' | 'direct'>('overview')
const detailOpen = ref(false)
const detailFields = computed<readonly TableDetailField[]>(() => {
  if (!user.value) return []
  return [
    { key: 'id', label: t('users.id'), value: String(user.value.id) },
    { key: 'username', label: t('users.username'), value: user.value.username },
    { key: 'email', label: t('users.email'), value: user.value.email },
    { key: 'status', label: t('users.status'), status: user.value.active },
  ]
})

async function load(): Promise<void> {
  busy.value = true
  error.value = null
  try {
    user.value = await getCurrentUser()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}

onMounted(() => void load())
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('security.usersTitle') }}</h1>
      <p>{{ t('security.usersDescription') }}</p>
    </div>
    <button type="button" class="secondary-button" :disabled="busy" @click="void load">
      ↻ {{ t('common.refresh') }}
    </button>
  </div>
  <p v-if="error" class="inline-error" role="alert">{{ t('security.requestError') }}</p>
  <section class="content-panel user-panel">
    <div v-if="busy && !user" class="empty-state" role="status">{{ t('list.loading') }}</div>
    <template v-else-if="user">
      <div class="user-row">
        <div>
          <strong>{{ user.username }}</strong>
          <small>#{{ user.id }} · {{ user.email }}</small>
        </div>
        <div class="user-row-actions">
          <TableStatus :status="user.active" />
          <button type="button" class="secondary-button" @click="detailOpen = true">
            {{ t('common.view') }}
          </button>
        </div>
      </div>
      <nav class="security-tabs" :aria-label="t('security.userTabsLabel')">
        <button
          v-for="item in ['overview', 'groups', 'direct'] as const"
          :key="item"
          type="button"
          :class="{ active: tab === item }"
          :aria-current="tab === item ? 'page' : undefined"
          @click="tab = item"
        >
          {{ t(`security.userTabs.${item}`) }}
        </button>
      </nav>
      <div v-if="tab === 'overview'" class="user-copy">
        <dl class="security-definition-list">
          <div>
            <dt>{{ t('users.username') }}</dt>
            <dd>{{ user.username }}</dd>
          </div>
          <div>
            <dt>{{ t('users.email') }}</dt>
            <dd>{{ user.email }}</dd>
          </div>
          <div>
            <dt>{{ t('users.status') }}</dt>
            <dd>{{ user.status || t('list.noValue') }}</dd>
          </div>
        </dl>
        <p class="security-note">{{ t('security.userReadOnlyNote') }}</p>
      </div>
      <div v-else-if="tab === 'groups'" class="user-copy">
        <h2>{{ t('security.userGroups') }}</h2>
        <div class="tag-list">
          <span v-for="group in user.groups" :key="group" class="tag">{{ group }}</span>
          <span v-if="user.groups.length === 0" class="security-note">{{
            t('security.noGroups')
          }}</span>
        </div>
      </div>
      <div v-else class="user-copy">
        <h2>{{ t('security.directPermissions') }}</h2>
        <p class="security-note">{{ t('security.directPermissionsUnavailable') }}</p>
      </div>
    </template>
    <p v-else class="empty-state">{{ t('security.noCurrentUser') }}</p>
  </section>
  <TableDetailModal
    :open="detailOpen"
    :title="t('users.detailTitle')"
    :fields="detailFields"
    @close="detailOpen = false"
  />
</template>

<style scoped>
.user-panel {
  display: grid;
  gap: 16px;
  padding: 18px 20px 20px;
}
.user-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px;
  border: 1px solid #dce6ec;
  border-radius: 6px;
}
.user-row div:first-child {
  display: grid;
  gap: 4px;
}
.user-row strong {
  color: #173c59;
  font-size: 14px;
}
.user-row small {
  color: #7b8d9c;
  font-size: 11px;
}
.user-row-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.security-tabs {
  display: flex;
  gap: 6px;
  border-bottom: 1px solid #e3ebef;
}
.security-tabs button {
  padding: 9px 12px;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: #60798d;
  font-size: 12px;
}
.security-tabs button.active {
  border-bottom-color: #0873b8;
  color: #075f9b;
  font-weight: 700;
}
.user-copy {
  display: grid;
  gap: 12px;
}
.user-copy h2 {
  margin: 0;
  color: #153956;
  font-size: 16px;
}
.security-definition-list {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}
.security-definition-list div {
  padding: 12px;
  border: 1px solid #dce6ec;
  border-radius: 6px;
}
.security-definition-list dt {
  color: #708499;
  font-size: 11px;
}
.security-definition-list dd {
  margin: 5px 0 0;
  color: #203f58;
  font-size: 13px;
  font-weight: 700;
}
.security-note {
  margin: 0;
  color: #6c8191;
  font-size: 12px;
  line-height: 1.6;
}
.tag-list {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
}
.tag {
  display: inline-flex;
  padding: 4px 8px;
  border: 1px solid #b8d5f1;
  background: #f1f7ff;
  color: #1872be;
  font-size: 11px;
}
.inline-error {
  margin: 0 0 12px;
  color: #b64444;
  font-size: 12px;
}
@media (max-width: 700px) {
  .user-row {
    align-items: flex-start;
    flex-direction: column;
  }
  .security-definition-list {
    grid-template-columns: 1fr;
  }
  .user-panel {
    padding: 15px 12px;
  }
}
</style>
