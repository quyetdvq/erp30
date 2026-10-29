import { describe, expect, it } from 'vitest'
import {
  STANDARD_PAGE_SIZE_OPTIONS,
  CUSTOMER_PAGE_SIZE_OPTIONS,
  pageSizeOptionsFor,
} from '../listPageSizePolicy'

describe('API-aligned list page size options', () => {
  it('preserves existing P1 catalog 200 cap', () => {
    expect(pageSizeOptionsFor(200)).toEqual(STANDARD_PAGE_SIZE_OPTIONS)
    expect(STANDARD_PAGE_SIZE_OPTIONS[STANDARD_PAGE_SIZE_OPTIONS.length - 1]).toBe(200)
  })
  it('limits Customer UI to Customer API cap 100', () => {
    expect(pageSizeOptionsFor(100)).toEqual(CUSTOMER_PAGE_SIZE_OPTIONS)
    expect(CUSTOMER_PAGE_SIZE_OPTIONS).not.toContain(200)
    expect(CUSTOMER_PAGE_SIZE_OPTIONS[CUSTOMER_PAGE_SIZE_OPTIONS.length - 1]).toBe(100)
  })
})
