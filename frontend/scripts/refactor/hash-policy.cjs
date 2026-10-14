/** Frontend equivalence is strict; backend and OpenAPI are independently maintained dependencies. */
const EXTERNAL_CONTRACT_FILES = new Set([
  'backend/app/Config/Routes.php',
  'contracts/openapi/openapi.yaml',
])

function compareCriticalHashes(expected, actual) {
  const frontend = []
  const external = []
  const paths = new Set([...Object.keys(expected), ...Object.keys(actual)])
  for (const filename of [...paths].sort()) {
    if (expected[filename] === actual[filename]) continue
    const difference = {
      file: filename,
      baseline: expected[filename] ?? null,
      current: actual[filename] ?? null,
    }
    if (EXTERNAL_CONTRACT_FILES.has(filename)) external.push(difference)
    else frontend.push(difference)
  }
  return { frontend, external }
}

module.exports = { compareCriticalHashes }
