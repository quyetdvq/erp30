/**
 * Contact address display helpers. Never display a raw province/ward ID as a name.
 * Prefer a matching location record and fall back to persisted P1 Contact names.
 */
export type ContactGeography = {
  province_id: string | null
  ward_id: string | null
  province_name?: string | null
  ward_name?: string | null
}

type ProvinceLookup = { id: string | number; name: string | null }
type WardLookup = ProvinceLookup & { province_id: string | number; name_full?: string | null }

function displayName(value: string | null | undefined): string | null {
  return value?.trim() || null
}

export function contactProvinceLabel(contact: ContactGeography, province?: ProvinceLookup | null): string | null {
  if (!contact.province_id) return null
  if (province && String(province.id) === String(contact.province_id)) {
    return displayName(province.name) ?? displayName(contact.province_name)
  }
  return displayName(contact.province_name)
}

export function contactWardLabel(contact: ContactGeography, ward?: WardLookup | null): string | null {
  if (!contact.ward_id) return null
  if (ward && String(ward.id) === String(contact.ward_id) &&
      (!contact.province_id || String(ward.province_id) === String(contact.province_id))) {
    return displayName(ward.name_full) ?? displayName(ward.name) ?? displayName(contact.ward_name)
  }
  return displayName(contact.ward_name)
}
