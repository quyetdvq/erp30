/**
 * Read only locally built assets. This report is diagnostic: maxSize is a
 * Rolldown target, not a hard chunk cap, and raw/gzip bytes are not a network
 * waterfall or a Core Web Vitals measurement.
 *
 * Usage (after pnpm run build): pnpm run bundle:report
 */
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { resolve, join } from 'node:path'
import { gzipSync } from 'node:zlib'

const dist = resolve(process.cwd(), 'dist')
const assets = join(dist, 'assets')
let files
let html
try {
  files = readdirSync(assets).filter((file) => file.endsWith('.js'))
  html = readFileSync(join(dist, 'index.html'), 'utf8')
} catch {
  process.stderr.write('Build artifacts not found. Run `pnpm run build` before `pnpm run bundle:report`.\n')
  process.exitCode = 1
}

if (files && html) {
  const chunks = files.map((name) => ({
    name,
    raw: statSync(join(assets, name)).size,
    gzip: gzipSync(readFileSync(join(assets, name))).length,
  }))
  chunks.sort((a, b) => b.raw - a.raw)
  const initial = new Set(
    [...html.matchAll(/(?:src|href)=["'](?:\/)?assets\/([^"']+\.js)["']/g)].map((match) => match[1]),
  )
  const initiallyLinked = chunks.filter((chunk) => initial.has(chunk.name))
  const naive = chunks.filter((chunk) => /^naive-ui(?:\d+)?[-~]/.test(chunk.name))
  const format = (bytes) => `${(bytes / 1024).toFixed(1)} KiB`
  console.log(`JavaScript chunks: ${chunks.length}`)
  console.log(`Total emitted JavaScript (raw / gzip): ${format(chunks.reduce((sum, chunk) => sum + chunk.raw, 0))} / ${format(chunks.reduce((sum, chunk) => sum + chunk.gzip, 0))}`)
  console.log(`Directly linked from index.html (raw / gzip): ${format(initiallyLinked.reduce((sum, chunk) => sum + chunk.raw, 0))} / ${format(initiallyLinked.reduce((sum, chunk) => sum + chunk.gzip, 0))}`)
  console.log(`Naive UI chunks: ${naive.length}; largest: ${naive.length ? `${naive[0].name} (${format(naive[0].raw)} / ${format(naive[0].gzip)})` : 'none explicitly grouped'}`)
  const naiveRaw = naive.reduce((sum, chunk) => sum + chunk.raw, 0)
  const naiveGzip = naive.reduce((sum, chunk) => sum + chunk.gzip, 0)
  console.log(`Naive UI combined (raw / gzip): ${format(naiveRaw)} / ${format(naiveGzip)}`)
  console.log('Largest five JavaScript chunks:')
  for (const chunk of chunks.slice(0, 5)) {
    console.log(`  ${chunk.name}: ${format(chunk.raw)} / ${format(chunk.gzip)}`)
  }
  console.log('Reference (Batch 3): naive-ui raw 664.51 kB / gzip 191.30 kB (Vite build output).')
  console.log('Compare the initial network waterfall and real route navigation separately; a smaller chunk is not itself proof of faster load.')
}
