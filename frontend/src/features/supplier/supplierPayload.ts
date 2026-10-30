import type { SupplierCreate, SupplierPatch, SupplierCompanyUsagePut, SupplierSetState } from '@/api/generated/types.gen'
import { expectedSupplierVersion } from '@/services/supplier'

export type SupplierDraft = {
  supplier_code: string
  name: string
  supplier_type: 'INDIVIDUAL' | 'COMPANY'
  phone: string
  email: string
  tax_code: string
  address: string
  province_id: string
  ward_id: string
  payment_term_code: '' | 'IMMEDIATE' | 'NET15' | 'NET30' | 'NET60'
  all_companies: boolean
  company_ids: string[]
}

export function emptySupplierDraft(): SupplierDraft {
  return {
    supplier_code: '', name: '', supplier_type: 'COMPANY', phone: '', email: '',
    tax_code: '', address: '', province_id: '', ward_id: '', payment_term_code: '',
    all_companies: true, company_ids: [],
  }
}

export function validSupplierDraft(draft: SupplierDraft): boolean {
  const normalized = draft.phone.replace(/[\s().-]+/g, '')
  return draft.name.trim().length > 0 && draft.address.trim().length > 0 &&
    /^\+?[0-9]{7,15}$/.test(normalized) && !!draft.province_id && !!draft.ward_id &&
    (draft.all_companies ? draft.company_ids.length === 0 : draft.company_ids.length > 0)
}

/** Only whitelisted profile fields. The server independently validates every field. */
export function supplierCreateBody(draft: SupplierDraft): SupplierCreate {
  if (!validSupplierDraft(draft)) throw new Error('SUPPLIER_PROFILE_INVALID')
  return {
    ...(draft.supplier_code.trim() ? { supplier_code: draft.supplier_code.trim() } : {}),
    name: draft.name.trim(), supplier_type: draft.supplier_type,
    supplier_group_code: 'STANDARD', phone: draft.phone.trim(),
    email: draft.email.trim() || null, tax_code: draft.tax_code.trim() || null,
    address: draft.address.trim(), province_id: draft.province_id, ward_id: draft.ward_id,
    payment_term_code: draft.payment_term_code || null,
    all_companies: draft.all_companies,
    company_ids: draft.all_companies ? [] : [...draft.company_ids],
  }
}

export function supplierPatchBody(draft: SupplierDraft, rowVersion: string | number): SupplierPatch {
  if (!validSupplierDraft(draft)) throw new Error('SUPPLIER_PROFILE_INVALID')
  return {
    expected_version: expectedSupplierVersion(rowVersion),
    name: draft.name.trim(), supplier_type: draft.supplier_type,
    supplier_group_code: 'STANDARD', phone: draft.phone.trim(),
    email: draft.email.trim() || null, tax_code: draft.tax_code.trim() || null,
    address: draft.address.trim(), province_id: draft.province_id, ward_id: draft.ward_id,
    payment_term_code: draft.payment_term_code || null,
  }
}

export function supplierUsageBody(draft: SupplierDraft, rowVersion: string | number): SupplierCompanyUsagePut {
  if (draft.all_companies ? draft.company_ids.length !== 0 : draft.company_ids.length === 0) {
    throw new Error('SUPPLIER_USAGE_INVALID')
  }
  return { expected_version: expectedSupplierVersion(rowVersion),
    all_companies: draft.all_companies, company_ids: draft.all_companies ? [] : [...draft.company_ids] }
}

export function supplierStateBody(status: string, rowVersion: string | number): SupplierSetState {
  if (!/^[A-Z][A-Z0-9_:-]{0,19}$/.test(status)) throw new Error('SUPPLIER_STATE_INVALID')
  return { expected_version: expectedSupplierVersion(rowVersion), status }
}
