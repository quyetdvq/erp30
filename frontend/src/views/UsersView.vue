<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { TableRowActions, TableStatus } from '@/components/lists'
import { getGroups, getUsers, saveUser, type Group, type ManagedUser } from '@/services/admin'

const { t } = useI18n()
const users = ref<ManagedUser[]>([])
const groups = ref<Group[]>([])
const search = ref('')
const busy = ref(false)
const error = ref('')
const modalOpen = ref(false)
const editing = ref<ManagedUser | null>(null)
const form = ref({ username: '', email: '', password: '', groups: [] as string[], active: true })

const filteredUsers = computed(() =>
  users.value.filter((user) =>
    `${user.username} ${user.email || ''}`.toLowerCase().includes(search.value.toLowerCase()),
  ),
)

async function load(): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    ;[users.value, groups.value] = await Promise.all([getUsers(), getGroups()])
  } catch {
    error.value = 'Không thể tải danh sách user.'
  } finally {
    busy.value = false
  }
}

function openCreate(): void {
  editing.value = null
  form.value = { username: '', email: '', password: '', groups: ['user'], active: true }
  modalOpen.value = true
}
function openEdit(user: ManagedUser): void {
  editing.value = user
  form.value = {
    username: user.username,
    email: user.email || '',
    password: '',
    groups: [...user.groups],
    active: user.active,
  }
  modalOpen.value = true
}
async function submit(): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    await saveUser(form.value, editing.value?.id)
    modalOpen.value = false
    await load()
  } catch {
    error.value = 'Không thể lưu user.'
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="breadcrumbs">⌂ <span>›</span> Thông tin và kiểm soát <span>›</span> Người dùng</div>
  <div class="page-heading">
    <div>
      <h1>Người dùng</h1>
      <p>Quản lý tài khoản, trạng thái và nhóm quyền.</p>
    </div>
    <button class="primary-button" type="button" @click="openCreate">＋ Tạo người dùng</button>
  </div>
  <section class="content-panel">
    <div class="toolbar">
      <input
        v-model="search"
        class="field-input search-field"
        placeholder="Tìm theo tên tài khoản hoặc email..."
      /><button class="secondary-button" type="button" @click="load">↻ Làm mới</button>
    </div>
    <p v-if="error" class="inline-error">{{ error }}</p>
    <div v-if="busy && users.length === 0" class="empty-state">Đang tải dữ liệu...</div>
    <div v-else class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>Tài khoản</th>
            <th>Email</th>
            <th>Nhóm</th>
            <th>Trạng thái</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in filteredUsers" :key="user.id">
            <td>
              <strong>{{ user.username }}</strong
              ><small class="subline">#{{ user.id }}</small>
            </td>
            <td>{{ user.email || '—' }}</td>
            <td>
              <span v-for="group in user.groups" :key="group" class="tag">{{ group }}</span>
            </td>
            <td>
              <TableStatus :status="user.active" />
            </td>
            <td>
              <TableRowActions
                :show-edit="true"
                :edit-label="t('common.edit')"
                @edit="openEdit(user)"
              />
            </td>
          </tr>
          <tr v-if="filteredUsers.length === 0">
            <td colspan="5" class="empty-state">Chưa có user phù hợp.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
  <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
    <section class="modal-card">
      <header>
        <div>
          <strong>{{ editing ? 'Chỉnh sửa user' : 'Tạo user mới' }}</strong
          ><small>Thông tin đăng nhập và nhóm quyền</small>
        </div>
        <button class="icon-button" aria-label="Đóng" @click="modalOpen = false">×</button>
      </header>
      <form @submit.prevent="submit">
        <div class="form-row">
          <label>Tên tài khoản</label><input v-model="form.username" class="field-input" required />
        </div>
        <div class="form-row">
          <label>Email</label
          ><input v-model="form.email" class="field-input" type="email" required />
        </div>
        <div v-if="!editing" class="form-row">
          <label>Mật khẩu</label
          ><input
            v-model="form.password"
            class="field-input"
            type="password"
            minlength="8"
            required
          />
        </div>
        <div class="form-row">
          <label>Nhóm quyền</label>
          <div class="group-checks">
            <label v-for="group in groups" :key="group.id"
              ><input v-model="form.groups" type="checkbox" :value="group.code" />
              {{ group.name }}</label
            >
          </div>
        </div>
        <label class="check-line"
          ><input v-model="form.active" type="checkbox" /> Tài khoản đang hoạt động</label
        >
        <p v-if="error" class="form-error">{{ error }}</p>
        <footer>
          <button class="secondary-button" type="button" @click="modalOpen = false">Hủy</button
          ><button class="primary-button" type="submit" :disabled="busy">Lưu thay đổi</button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.table-scroll {
  overflow-x: auto;
}
.subline {
  display: block;
  margin-top: 3px;
  color: #8a99a8;
  font-size: 10px;
}
.inline-error {
  margin: 0;
  padding: 10px 14px;
  color: #b64444;
  font-size: 12px;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 40;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(18 38 54 / 45%);
}
.modal-card {
  width: min(100%, 520px);
  max-height: calc(100vh - 40px);
  overflow: auto;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}
.modal-card > header,
.modal-card > form > footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 18px;
  border-bottom: 1px solid #e4ebef;
}
.modal-card header div {
  display: grid;
  gap: 4px;
}
.modal-card header strong {
  color: #173c59;
}
.modal-card header small {
  color: #8292a0;
  font-size: 11px;
}
.modal-card form {
  padding: 18px;
}
.modal-card form footer {
  justify-content: flex-end;
  gap: 8px;
  margin: 20px -18px -18px;
  border-top: 1px solid #e4ebef;
  border-bottom: 0;
}
.group-checks {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
  padding: 9px;
  border: 1px solid #d8e2e9;
  border-radius: 4px;
  color: #486176;
  font-size: 12px;
}
.check-line {
  display: flex;
  align-items: center;
  gap: 7px;
  color: #687f92;
  font-size: 12px;
}
</style>
