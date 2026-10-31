<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref } from 'vue'

import { apiClient } from '@/services/api'
import { useFormatters } from '@/composables/useFormatters'

type HealthResponse = {
  service: string
  status: string
  timestamp: string
}

type CheckState = 'idle' | 'loading' | 'success' | 'error'

const checkState = ref<CheckState>('idle')
const health = ref<HealthResponse | null>(null)
const errorMessage = ref('')
const formatters = useFormatters()

const endpoint = `${apiClient.defaults.baseURL ?? ''}/api/v1/health`

async function checkConnection(): Promise<void> {
  checkState.value = 'loading'
  health.value = null
  errorMessage.value = ''

  try {
    const response = await apiClient.get<HealthResponse>('/api/v1/health')
    health.value = response.data
    checkState.value = 'success'
  } catch (error: unknown) {
    checkState.value = 'error'

    if (axios.isAxiosError(error)) {
      errorMessage.value = error.response
        ? `Backend phản hồi HTTP ${error.response.status}.`
        : 'Không thể kết nối tới backend.'
    } else {
      errorMessage.value = 'Đã xảy ra lỗi không xác định.'
    }
  }
}

function formatTimestamp(timestamp: string): string {
  return formatters.formatDateTime(timestamp)
}

onMounted(checkConnection)
</script>

<template>
  <main class="page-shell">
    <section class="status-panel" aria-labelledby="page-title">
      <p class="eyebrow">Mini ERP · Phase 1</p>
      <h1 id="page-title">Kiểm tra kết nối hệ thống</h1>
      <p class="lead">
        Frontend đang gọi endpoint kiểm tra sức khỏe của backend qua kết nối HTTP thực tế.
      </p>

      <dl class="endpoint">
        <dt>Endpoint</dt>
        <dd>{{ endpoint }}</dd>
      </dl>

      <div class="result" :class="`result--${checkState}`" aria-live="polite">
        <span class="status-dot" aria-hidden="true"></span>
        <div>
          <strong v-if="checkState === 'loading'">Đang kiểm tra...</strong>
          <strong v-else-if="checkState === 'success'">Backend đang hoạt động</strong>
          <strong v-else-if="checkState === 'error'">Kết nối chưa thành công</strong>
          <strong v-else>Chưa kiểm tra</strong>

          <p v-if="checkState === 'success' && health" class="result-detail">
            {{ health.service }} · {{ formatTimestamp(health.timestamp) }}
          </p>
          <p v-else-if="checkState === 'error'" class="result-detail">{{ errorMessage }}</p>
        </div>
      </div>

      <button
        data-ds-icon="refresh"
        type="button"
        class="check-button primary-button"
        :disabled="checkState === 'loading'"
        @click="checkConnection"
      >
        {{ checkState === 'loading' ? 'Đang kiểm tra' : 'Kiểm tra lại' }}
      </button>
    </section>
  </main>
</template>

<style scoped>
.page-shell {
  display: grid;
  min-height: 100vh;
  place-items: center;
  padding: 24px;
}

.status-panel {
  width: min(100%, 680px);
  padding: clamp(28px, 6vw, 56px);
  background: var(--ds-bg-surface);
  border: 1px solid var(--ds-border-subtle);
  border-radius: 8px;
  box-shadow: var(--ds-shadow-popover);
}

.eyebrow {
  margin: 0 0 12px;
  color: var(--ds-primary);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

h1 {
  margin: 0;
  font-size: clamp(1.7rem, 5vw, 2.5rem);
  line-height: 1.1;
}

.lead {
  max-width: 54ch;
  margin: 16px 0 28px;
  color: var(--ds-text-secondary);
  line-height: 1.6;
}

.endpoint {
  display: grid;
  gap: 6px;
  margin: 0 0 24px;
}

.endpoint dt {
  color: var(--ds-text-muted);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.endpoint dd {
  margin: 0;
  overflow-wrap: anywhere;
  color: var(--ds-text-primary);
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', monospace;
  font-size: 0.92rem;
}

.result {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  min-height: 72px;
  padding: 16px;
  border: 1px solid var(--ds-border-subtle);
  border-radius: 6px;
  background: var(--ds-bg-subtle);
}

.status-dot {
  width: 10px;
  height: 10px;
  flex: 0 0 auto;
  margin-top: 5px;
  border-radius: 50%;
  background: var(--ds-text-muted);
}

.result--loading .status-dot {
  background: var(--ds-warning);
}

.result--success .status-dot {
  background: var(--ds-success);
}

.result--error .status-dot {
  background: var(--ds-danger);
}

.result strong {
  display: block;
  line-height: 1.4;
}

.result-detail {
  margin: 4px 0 0;
  color: var(--ds-text-secondary);
  font-size: 0.9rem;
  line-height: 1.5;
}

/* Full-width action layout only; appearance/hover/disabled come from buttons.css. */
.check-button { width: 100%; margin-top: 20px; }
</style>
