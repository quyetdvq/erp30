<script setup lang="ts">
import { computed } from 'vue'
import { NTreeSelect, type TreeSelectOption } from 'naive-ui'

import type { Organization } from '@/api/generated/types.gen'

const props = withDefaults(
  defineProps<{
    id?: string
    modelValue?: string | null
    organizations: readonly Organization[]
    placeholder: string
    disabled?: boolean
    clearable?: boolean
  }>(),
  { id: undefined, modelValue: null, disabled: false, clearable: false },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const treeOptions = computed<TreeSelectOption[]>(() => {
  const byId = new Map(
    props.organizations.map((organization) => [String(organization.id), organization]),
  )
  const children = new Map<string, Organization[]>()

  for (const organization of props.organizations) {
    const parentId = organization.parent_id ? String(organization.parent_id) : 'ROOT'
    const key = byId.has(parentId) ? parentId : 'ROOT'
    children.set(key, [...(children.get(key) ?? []), organization])
  }

  for (const values of children.values()) {
    values.sort((left, right) =>
      `${left.code}${left.name}`.localeCompare(`${right.code}${right.name}`),
    )
  }

  function nodeFor(organization: Organization, ancestors: Set<string>): TreeSelectOption {
    const id = String(organization.id)
    if (ancestors.has(id)) {
      return { key: id, label: `${organization.name} (${organization.code})` }
    }
    const nextAncestors = new Set(ancestors)
    nextAncestors.add(id)
    const nested = (children.get(id) ?? []).map((child) => nodeFor(child, nextAncestors))
    return {
      key: id,
      label: `${organization.name} (${organization.code})`,
      ...(nested.length > 0 ? { children: nested } : {}),
    }
  }

  return (children.get('ROOT') ?? []).map((organization) => nodeFor(organization, new Set()))
})

function updateValue(value: string | number | Array<string | number> | null): void {
  if (Array.isArray(value)) return
  emit('update:modelValue', value === null ? '' : String(value))
}
</script>

<template>
  <NTreeSelect
    :id="props.id"
    :value="props.modelValue || null"
    :options="treeOptions"
    :placeholder="props.placeholder"
    :disabled="props.disabled"
    :clearable="props.clearable"
    :default-expand-all="true"
    :filterable="true"
    :show-path="true"
    separator=" / "
    class="organization-tree-select"
    @update:value="updateValue"
  />
</template>

<style scoped>
.organization-tree-select {
  min-width: min(320px, 100%);
}
</style>
