/**
 * UI page size choices must match each resource's published API cap.
 * P1 read catalog/Contact currently cap 200; Customer caps 100.
 * Do not globally lower an existing API's page-size contract.
 */
export const STANDARD_PAGE_SIZE_OPTIONS = [10, 15, 20, 25, 50, 100, 200] as const
export const CUSTOMER_PAGE_SIZE_OPTIONS = STANDARD_PAGE_SIZE_OPTIONS.filter((size) => size <= 100)

export function pageSizeOptionsFor(maxPageSize: 100 | 200): readonly number[] {
  return maxPageSize === 100 ? CUSTOMER_PAGE_SIZE_OPTIONS : STANDARD_PAGE_SIZE_OPTIONS
}
