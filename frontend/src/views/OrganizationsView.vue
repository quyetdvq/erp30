<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { getOrganizations, saveOrganization, type Organization } from '@/services/admin'

const organizations = ref<Organization[]>([])
const search = ref('')
const busy = ref(false)
const error = ref('')
const modalOpen = ref(false)
const form = ref({
  type: 'COMPANY',
  code: '',
  name: '',
  parent_id: null as number | null,
  active_from: '',
  active_to: '',
})

const filtered = computed(() =>
  organizations.value.filter((item) =>
    `${item.code} ${item.name}`.toLowerCase().includes(search.value.toLowerCase()),
  ),
)

async function load(): Promise<void> {
  busy.value = true
  try {
    organizations.value = await getOrganizations()
  } catch {
    error.value = 'Không thể tải danh sách tổ chức.'
  } finally {
    busy.value = false
  }
}
function openCreate(): void {
  form.value = {
    type: 'COMPANY',
    code: '',
    name: '',
    parent_id: null,
    active_from: '',
    active_to: '',
  }
  modalOpen.value = true
}
async function save(): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    await saveOrganization(form.value)
    modalOpen.value = false
    await load()
  } catch {
    error.value = 'Không thể lưu tổ chức.'
  } finally {
    busy.value = false
  }
}
onMounted(load)
</script>

<template>
  <div class="breadcrumbs">
    ⌂ <span>›</span> Quản trị tổ chức và dữ liệu nền <span>›</span> Tổ chức
  </div>
  <div class="page-heading">
    <div>
      <h1>Tổ chức</h1>
      <p>Quản lý cây công ty, chi nhánh và các đơn vị vận hành.</p>
    </div>
    <button class="primary-button" type="button" @click="openCreate">＋ Tạo công ty</button>
  </div>
  <section class="content-panel">
    <div class="toolbar">
      <input
        v-model="search"
        class="field-input search-field"
        placeholder="Tìm theo mã hoặc tên tổ chức..."
      /><button class="secondary-button" type="button" @click="load">↻ Làm mới</button>
    </div>
    <p v-if="error" class="inline-error">{{ error }}</p>
    <div v-if="busy && organizations.length === 0" class="empty-state">Đang tải dữ liệu...</div>
    <div v-else class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>Mã</th>
            <th>Tên tổ chức</th>
            <th>Loại</th>
            <th>Cấp cha</th>
            <th>Trạng thái</th>
            <th>Hiệu lực</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in filtered" :key="item.id">
            <td>
              <strong>{{ item.code }}</strong>
            </td>
            <td>{{ item.name }}</td>
            <td>
              <span class="tag">{{ item.type }}</span>
            </td>
            <td>{{ item.parent_name || '—' }}</td>
            <td>
              <span class="status-pill" :class="{ 'status-pill--off': item.status !== 'ACTIVE' }">{{
                item.status
              }}</span>
            </td>
            <td>{{ item.active_from || '—' }}</td>
          </tr>
          <tr v-if="filtered.length === 0">
            <td colspan="6" class="empty-state">Chưa có công ty hoặc đơn vị phù hợp.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
  <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
    <section class="modal-card">
      <header>
        <div>
          <strong>Tạo công ty / đơn vị</strong><small>Thông tin Organization Context</small>
        </div>
        <button class="icon-button" @click="modalOpen = false">×</button>
      </header>
      <form @submit.prevent="save">
        <div class="form-row">
          <label>Loại tổ chức</label
          ><select v-model="form.type" class="field-select">
            <option>GROUP</option>
            <option>COMPANY</option>
            <option>BRANCH</option>
            <option>PLANT</option>
            <option>WAREHOUSE</option>
          </select>
        </div>
        <div class="form-row">
          <label>Mã</label><input v-model="form.code" class="field-input" required />
        </div>
        <div class="form-row">
          <label>Tên</label><input v-model="form.name" class="field-input" required />
        </div>
        <div class="form-row">
          <label>Cấp cha</label
          ><select v-model="form.parent_id" class="field-select">
            <option :value="null">Không có</option>
            <option v-for="item in organizations" :key="item.id" :value="item.id">
              {{ item.code }} · {{ item.name }}
            </option>
          </select>
        </div>
        <div class="form-grid">
          <div class="form-row">
            <label>Hiệu lực từ</label
            ><input v-model="form.active_from" class="field-input" type="date" />
          </div>
          <div class="form-row">
            <label>Hiệu lực đến</label
            ><input v-model="form.active_to" class="field-input" type="date" />
          </div>
        </div>
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
.modal-card header {
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
.modal-card footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin: 20px -18px -18px;
  padding: 15px 18px;
  border-top: 1px solid #e4ebef;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
@media (max-width: 500px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
