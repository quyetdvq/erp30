import { createApp } from 'vue'
import { createPinia } from 'pinia'

import './styles/global.css'

import { NIcon } from 'naive-ui'

import {
  HomeOutline,
  MapOutline,
  LocationOutline,
  BusinessOutline,
  StorefrontOutline,
  ConstructOutline,
  ArchiveOutline,
  PeopleOutline,
  BriefcaseOutline,
  IdCardOutline,
  FolderOpenOutline,
  CubeOutline,
  ScaleOutline,
  LayersOutline,
  PricetagsOutline,
  ShieldCheckmarkOutline,
  AddOutline,
  CreateOutline,
  SaveOutline,
  EyeOutline,
  TrashOutline,
  Refresh,
  AddCircleOutline,
} from '@vicons/ionicons5'

const globalIcons = {
  HomeOutline,
  MapOutline,
  LocationOutline,
  BusinessOutline,
  StorefrontOutline,
  ConstructOutline,
  ArchiveOutline,
  PeopleOutline,
  BriefcaseOutline,
  IdCardOutline,
  FolderOpenOutline,
  CubeOutline,
  ScaleOutline,
  LayersOutline,
  PricetagsOutline,
  ShieldCheckmarkOutline,
  AddOutline,
  CreateOutline,
  SaveOutline,
  EyeOutline,
  TrashOutline,
  Refresh,
  AddCircleOutline,
}

import App from './App.vue'
import { i18n } from './i18n'
import router from './router'

const app = createApp(App)

app.use(createPinia())
app.use(i18n)
app.use(router)

// Chèn sau const app = createApp(App), trước app.mount(...)
app.component('NIcon', NIcon)

Object.entries(globalIcons).forEach(([name, component]) => {
  app.component(name, component)
})

app.mount('#app')
