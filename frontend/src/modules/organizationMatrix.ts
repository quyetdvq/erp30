import type { Organization } from '@/api/generated/types.gen'

export type OrganizationType = Organization['type']

export const ORGANIZATION_PARENT_MATRIX: Record<
  OrganizationType | 'ROOT',
  readonly OrganizationType[]
> = {
  ROOT: ['GROUP'],
  GROUP: ['COMPANY'],
  COMPANY: ['BRANCH', 'PLANT', 'WAREHOUSE'],
  BRANCH: ['PLANT', 'WAREHOUSE'],
  PLANT: ['WAREHOUSE'],
  WAREHOUSE: [],
}

export function allowedOrganizationChildren(
  parentType: OrganizationType | null,
): readonly OrganizationType[] {
  return ORGANIZATION_PARENT_MATRIX[parentType ?? 'ROOT']
}
