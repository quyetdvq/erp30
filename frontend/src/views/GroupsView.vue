<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { getGroupPermissions, getGroups, saveGroup, saveGroupPermissions, type Group, type ResourceAction } from '@/services/admin'

const groups = ref<Group[]>([])
const catalog = ref<ResourceAction[]>([])
const assigned = ref<number[]>([])
const selected = ref<Group | null>(null)
const busy = ref(false)
const error = ref('')
const modalOpen = ref(false)
const form = ref({ code: '', name: '', description: '', status: 'ACTIVE' })

async function load(): Promise<void> { busy.value = true; try { groups.value = await getGroups() } catch { error.value = 'Không thể tải group.' } finally { busy.value = false } }
async function selectGroup(group: Group): Promise<void> { selected.value = group; try { const response = await getGroupPermissions(group.id); catalog.value = response.data; assigned.value = response.assigned } catch { error.value = 'Không thể tải permission catalog.' } }
function openCreate(): void { form.value = { code: '', name: '', description: '', status: 'ACTIVE' }; modalOpen.value = true }
async function save(): Promise<void> { busy.value = true; try { await saveGroup(form.value); modalOpen.value = false; await load() } catch { error.value = 'Không thể lưu group.' } finally { busy.value = false } }
async function savePermissions(): Promise<void> { if (!selected.value) return; busy.value = true; try { await saveGroupPermissions(selected.value.id, assigned.value) } catch { error.value = 'Không thể lưu ma trận quyền.' } finally { busy.value = false } }
onMounted(load)
</script>

<template>
  <div class="breadcrumbs">⌂ <span>›</span> Thông tin và kiểm soát <span>›</span> Groups</div>
  <div class="page-heading">
    <div>
      <h1>Groups & permission matrix</h1>
      <p>Danh mục group dùng chung với CodeIgniter Shield.</p>
    </div>
    <button class="primary-button" type="button" @click="openCreate">＋ Tạo group</button>
  </div>
  <div class="split-layout">
    <section class="content-panel">
      <div class="toolbar"><strong class="toolbar-title">Danh sách group</strong>
        <button class="secondary-button" type="button" @click="load">↻</button>
      </div>
      <div class="group-list">
        <button v-for="group in groups" :key="group.id" type="button" class="group-item"
          :class="{ selected: selected?.id === group.id }" @click="selectGroup(group)"><span><strong>{{ group.name
              }}</strong><small>{{ group.code }} · {{ group.permission_count }}
              quyền</small></span><span>›</span></button>
        <div v-if="groups.length === 0" class="empty-state">Chưa có group.</div>
      </div>
    </section>
    <section class="content-panel permission-panel">
      <div v-if="!selected" class="empty-state">Chọn một group để xem permission matrix.</div><template v-else>
        <div class="toolbar"><strong class="toolbar-title">{{ selected.name }}</strong><button class="primary-button"
            type="button" :disabled="busy" @click="savePermissions">Lưu quyền</button></div>
        <div class="permission-grid"><label v-for="action in catalog" :key="action.id" class="permission-item"><input
              v-model="assigned" type="checkbox" :value="action.id" /><span><strong>{{ action.resource }}.{{
                action.action }}</strong><small>{{ action.description || action.resource_name }}</small></span></label>
          <div v-if="catalog.length === 0" class="empty-state">Chưa có resource action trong catalog.</div>
        </div>
      </template>
    </section>
  </div>
  <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
    <section class="modal-card">
      <header><strong>Tạo group</strong><button class="icon-button" @click="modalOpen = false">×</button></header>
      <form @submit.prevent="save">
        <div class="form-row"><label>Mã group</label><input v-model="form.code" class="field-input" required /></div>
        <div class="form-row"><label>Tên group</label><input v-model="form.name" class="field-input" required /></div>
        <div class="form-row"><label>Mô tả</label><textarea v-model="form.description"
            class="field-input textarea"></textarea></div>
        <footer><button class="secondary-button" type="button" @click="modalOpen = false">Hủy</button><button
            class="primary-button" type="submit">Lưu</button></footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.split-layout {
  display: grid;
  grid-template-columns: minmax(250px, .8fr) minmax(0, 1.4fr);
  gap: 16px;
}

.toolbar {
  align-items: center;
}

.toolbar-title {
  flex: 1;
  color: #254761;
  font-size: 13px;
}

.group-list {
  padding: 6px;
}

.group-item {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  padding: 12px 11px;
  border: 0;
  border-bottom: 1px solid #edf1f4;
  background: transparent;
  color: #3a5770;
  text-align: left;
}

.group-item span:first-child {
  display: grid;
  gap: 4px;
}

.group-item small {
  color: #8493a1;
  font-size: 10px;
}

.group-item:hover,
.group-item.selected {
  background: #edf7f8;
  color: #066ba9;
}

.permission-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 9px;
  padding: 14px;
}

.permission-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px;
  border: 1px solid #e0e8ed;
  border-radius: 4px;
  color: #2e4e68;
  font-size: 11px;
}

.permission-item span {
  display: grid;
  gap: 4px;
}

.permission-item small {
  color: #8594a1;
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
  width: min(100%, 470px);
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}

.modal-card header,
.modal-card footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 18px;
  border-bottom: 1px solid #e4ebef;
  color: #173c59;
}

.modal-card form {
  padding: 18px;
}

.modal-card footer {
  justify-content: flex-end;
  gap: 8px;
  margin: 18px -18px -18px;
  border-top: 1px solid #e4ebef;
  border-bottom: 0;
}

.textarea {
  height: 75px;
  padding-top: 8px;
  resize: vertical;
}

@media (max-width: 760px) {

  .split-layout,
  .permission-grid {
    grid-template-columns: 1fr;
  }
}
</style>
