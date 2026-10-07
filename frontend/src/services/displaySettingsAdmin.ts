import {
  getOrganizationTree,
  listDisplaySettingDefinitions,
  listDisplaySettingValues,
  resetDisplaySettings,
  updateDisplaySettings,
} from '@/api/generated/sdk.gen'
import { withTypedClient } from '@/api/client'
import type {
  DisplaySettingContext,
  DisplaySettingDefinition,
  DisplaySettingValue,
  Organization,
} from '@/api/generated/types.gen'
import { i18n } from '@/i18n'

export type DisplaySettingsContext = DisplaySettingContext

function languageHeaders() {
  return { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' }
}

export async function getDisplaySettingDefinitions(): Promise<DisplaySettingDefinition[]> {
  const response = await withTypedClient((client) =>
    listDisplaySettingDefinitions({
      client,
      headers: languageHeaders(),
      throwOnError: true,
    }),
  )
  return response.data.data ?? []
}

export async function getDisplaySettingValues(
  context: DisplaySettingsContext,
): Promise<DisplaySettingValue[]> {
  const response = await withTypedClient((client) =>
    listDisplaySettingValues({
      client,
      headers: languageHeaders(),
      query:
        context.type === 'SYSTEM'
          ? { context_type: 'SYSTEM' }
          : { context_type: 'ORGANIZATION', organization_id: context.organization_id },
      throwOnError: true,
    }),
  )
  return response.data.data ?? []
}

export async function setDisplaySettingOverrides(
  context: DisplaySettingsContext,
  changes: Array<{ key: string; value: unknown }>,
): Promise<DisplaySettingValue[]> {
  const response = await withTypedClient((client) =>
    updateDisplaySettings({
      client,
      headers: languageHeaders(),
      body: { context, changes },
      throwOnError: true,
    }),
  )
  return response.data.data ?? []
}

export async function resetDisplaySettingOverrides(
  context: DisplaySettingsContext,
  keys: string[],
): Promise<DisplaySettingValue[]> {
  const response = await withTypedClient((client) =>
    resetDisplaySettings({
      client,
      headers: languageHeaders(),
      body: { context, keys },
      throwOnError: true,
    }),
  )
  return response.data.data ?? []
}

export async function getDisplaySettingOrganizations(): Promise<Organization[]> {
  const response = await withTypedClient((client) =>
    getOrganizationTree({
      client,
      headers: languageHeaders(),
      query: { page_size: 200, sort: 'name.asc,id.asc' },
      throwOnError: true,
    }),
  )
  return response.data.data ?? []
}
