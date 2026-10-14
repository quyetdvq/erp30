const path = require('node:path')

/** Convert native paths to stable, repository-relative POSIX keys on Linux and Windows. */
function toRepoRelative(file, repoRoot, pathApi = path) {
  const relative = pathApi.isAbsolute(file) ? pathApi.relative(repoRoot, file) : file
  const normalized = relative.split(pathApi.sep).join('/')
  if (
    normalized === '..' ||
    normalized.startsWith('../') ||
    pathApi.isAbsolute(relative)
  ) {
    throw new Error(`File is outside the repository: ${file}`)
  }
  return normalized
}

module.exports = { toRepoRelative }
