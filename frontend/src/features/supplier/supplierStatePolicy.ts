/**
 * Presentation-only policy. PBAC, Lifecycle transition validity, and CAS are
 * always rechecked by the Supplier API when the user submits a transition.
 */
export type SupplierStateHint = {
  allowed_actions?: { setstate?: boolean } | null
  setstate_targets?: readonly string[] | null
}

export function supplierTransitionTargets(snapshot: SupplierStateHint | null | undefined): string[] {
  if (!snapshot || snapshot.allowed_actions?.setstate !== true ||
      !Array.isArray(snapshot.setstate_targets)) return []
  // Never manufacture fixed DRAFT/ACTIVE/INACTIVE transitions if metadata is missing.
  return [...new Set(snapshot.setstate_targets.filter(
    (code: unknown): code is string => typeof code === 'string' && /^[A-Z][A-Z0-9_:-]{0,19}$/.test(code),
  ))]
}

export function canSubmitSupplierTransition(snapshot: SupplierStateHint | null | undefined, target: string): boolean {
  return !!snapshot && snapshot.allowed_actions?.setstate === true &&
    supplierTransitionTargets(snapshot).includes(target)
}
