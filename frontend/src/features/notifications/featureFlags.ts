/** Rollout switch: hide real Inbox until worker, schema and API have passed DEV QA. */
export const inboxUiEnabled = import.meta.env.VITE_M11_NOTIFICATIONS_ENABLED === 'true'
