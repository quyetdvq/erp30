import type { ContactOwnerType as ApiContactOwnerType } from '@/api/generated/types.gen'

/**
 * P2 SUP-T06B1: Shared Contact resource ownership is explicit and fail-closed.
 * This map is NOT a permission grant. The server still decides both Contact
 * permissions and (for CUSTOMER/SUPPLIER) the parent's independent VIEW.
 */
const RESOURCE_BY_OWNER = {
  ORGANIZATION: 'organization_contact',
  EMPLOYEE: 'employee_contact',
  CUSTOMER: 'customer_contact',
  SUPPLIER: 'supplier_contact',
} as const satisfies Record<ApiContactOwnerType, string>

export type ContactOwnerType = keyof typeof RESOURCE_BY_OWNER

/** Unknown/noncanonical owner kinds never fall back to employee_contact. */
export function contactOwnerResource(owner: string | null | undefined): string {
  if (typeof owner !== 'string' || !Object.prototype.hasOwnProperty.call(RESOURCE_BY_OWNER, owner)) {
    return ''
  }
  return RESOURCE_BY_OWNER[owner as ContactOwnerType]
}
