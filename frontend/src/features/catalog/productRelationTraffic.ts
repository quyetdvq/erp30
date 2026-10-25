/** Frozen M7.4 relation preview accepts at most 50 Product IDs per request. */
export const RELATION_PREVIEW_BATCH_SIZE = 50

/**
 * Start bounded batches and publish each completed batch independently.
 * A superseded page/session never publishes; after any batch fails, late
 * batches cannot populate partial data behind the list's error handler.
 */
export async function loadProductRelationBatches<T>(
  ids: readonly string[],
  fetchBatch: (ids: readonly string[]) => Promise<T[]>,
  publishBatch: (ids: readonly string[], result: T[]) => void,
  isCurrent: () => boolean,
): Promise<void> {
  let failed = false
  const jobs: Promise<void>[] = []
  for (let start = 0; start < ids.length; start += RELATION_PREVIEW_BATCH_SIZE) {
    const batch = ids.slice(start, start + RELATION_PREVIEW_BATCH_SIZE)
    jobs.push(
      fetchBatch(batch).then((result) => {
        if (!failed && isCurrent()) publishBatch(batch, result)
      }),
    )
  }
  try {
    await Promise.all(jobs)
  } catch (error: unknown) {
    failed = true
    throw error
  }
}
