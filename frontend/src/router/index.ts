import { createRouter, createWebHistory } from 'vue-router'
import ConnectivityView from '@/views/ConnectivityView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'connectivity',
      component: ConnectivityView,
    },
  ],
})

export default router
