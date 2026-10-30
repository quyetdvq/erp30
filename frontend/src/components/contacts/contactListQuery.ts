import type { ListQuery } from '@/components/lists/listTypes'
import type { ContactOwnerType } from './contactOwnerPolicy'

/**
 * Single authorized Contact page, always scoped to the parent owner.
 * Do not force a status here: the default list must include ACTIVE, DRAFT,
 * INACTIVE, CLOSED and any server-defined Lifecycle states together.
 */
export function scopedContactListQuery(
  query: ListQuery,
  ownerType: ContactOwnerType,
  ownerId: string,
): ListQuery {
  const status = query.filter.status
  return {
    ...query,
    filter: {
      contact_type: ownerType,
      contact_ref_id: ownerId,
      ...(typeof status === 'string' && status ? { status } : {}),
    },
  }
}
