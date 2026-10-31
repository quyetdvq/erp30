// A deterministic, unauthenticated browser harness that mounts the *actual*
// shared ListTable and AppDialogHost. No production account, live API or DB.
import { createApp, defineComponent, h, ref } from 'vue'
import { darkTheme, NConfigProvider, NGlobalStyle } from 'naive-ui'
import { createPinia } from 'pinia'

import '../../src/styles/global.css'
import ListTable from '../../src/components/lists/ListTable.vue'
import AppDialogHost from '../../src/components/ui/AppDialogHost.vue'
import ApiErrorNoticeStack from '../../src/components/ui/ApiErrorNoticeStack.vue'
import { clearApiErrors, errorNotices } from '../../src/services/errorBoundary'
import { i18n } from '../../src/i18n'
import { naiveDarkOverrides, naiveLightOverrides } from '../../src/styles/naiveTheme'

const isDark = new URLSearchParams(window.location.search).get('theme') === 'dark'
document.documentElement.dataset.dsTheme = isDark ? 'dark' : 'light'

const columns = [
  { key: 'name', labelKey: 'customer.name', sortable: true },
  { key: 'customer_code', labelKey: 'customer.code', sortable: true },
]
const rows = [{ id: '1', name: 'Example customer', customer_code: 'CUS-0001' }]

const Fixture = defineComponent({
  setup() {
    const sort = ref('name')
    const dialogOpen = ref(false)
    const insertError = () => {
      errorNotices.value = [{
        id: '422:BAD_CUSTOMER:regression', status: 422, code: 'BAD_CUSTOMER',
        message: 'Customer validation failed while editing', requestId: 'regression',
      }]
    }
    const insertWarning = () => {
      errorNotices.value = [{
        id: '403:NOT_ALLOWED:regression', status: 403, code: 'NOT_ALLOWED',
        message: 'Not allowed to change this record', requestId: 'regression-auth',
      }]
    }
    clearApiErrors()

    return () => h(NConfigProvider, { theme: isDark ? darkTheme : undefined, themeOverrides: isDark ? naiveDarkOverrides : naiveLightOverrides }, {
      default: () => [
        h(NGlobalStyle),
        h('main', { style: 'max-width:760px;padding:30px' }, [
          h('p', { id: 'sort-value' }, sort.value),
          h(ListTable, {
            rows,
            columns,
            sort: sort.value,
            'onSort-change': (value: string) => { sort.value = value },
          }),
          h('button', { id: 'open-dialog', onClick: () => { dialogOpen.value = true } }, 'Open edit dialog'),
          h('button', { id: 'page-error', onClick: insertError }, 'Show page error'),
          h('button', { id: 'auth-error', onClick: insertWarning }, 'Show permission warning'),
        ]),
        h(AppDialogHost, { open: dialogOpen.value, onClose: () => { dialogOpen.value = false } }, {
          default: () => h('section', { class: 'ds-dialog-surface', role: 'dialog', 'aria-label': 'Edit customer' }, [
            h('header', [
              h('strong', 'Edit customer'),
              h('button', { id: 'close-dialog', type: 'button', onClick: () => { dialogOpen.value = false } }, 'Close dialog'),
            ]),
            h('div', { class: 'ds-modal__body' }, [
              h('button', { id: 'modal-error', type: 'button', onClick: insertError }, 'Trigger validation error'),
              h('label', ['Customer name', h('input', { value: 'Example' })]),
            ]),
            h('footer', 'Actions'),
          ]),
        }),
        h(ApiErrorNoticeStack),
      ],
    })
  },
})

createApp(Fixture).use(createPinia()).use(i18n).mount('#hf3-root')
