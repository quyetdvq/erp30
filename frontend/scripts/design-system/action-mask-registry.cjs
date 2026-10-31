// Test-side inspector for the user-maintained data-ds-icon SVG registry.
// Accept both percent-encoded SVG data and human-readable inline SVG data.
// The artwork and its SVG geometry belong to src/styles/buttons.css.
'use strict'

const ACTION_RULE = /^\s*\[data-ds-icon="([a-z-]+)"\]\s*\{\s*--ds-action-mask:\s*url\("([^"\r\n]+)"\);\s*\}\s*$/gm
const DECLARED_RULE = /^\s*\[data-ds-icon="([a-z-]+)"\]\s*\{/gm

function inspectMaskRegistry(css) {
  if (typeof css !== 'string') throw new Error('Mask registry requires CSS text')
  const declared = [...css.matchAll(DECLARED_RULE)].map(match => match[1])
  const rules = [...css.matchAll(ACTION_RULE)]
  if (declared.length !== rules.length) {
    throw new Error(`Expected one data-SVG mask per icon: found ${declared.length} selectors but ${rules.length} valid mask rules`)
  }
  const icons = new Map()
  for (const [, name, uri] of rules) {
    if (icons.has(name)) throw new Error(`Duplicate data-ds-icon="${name}" mask`)
    if (!uri.startsWith('data:image/svg+xml,')) throw new Error(`${name}: SVG mask must use a data:image/svg+xml URI`)
    const payload = uri.slice('data:image/svg+xml,'.length)
    if (!payload || /%(?![\da-fA-F]{2})/.test(payload)) throw new Error(`${name}: malformed percent encoding`)
    let svg
    try {
      svg = decodeURIComponent(payload).trim()
    } catch {
      throw new Error(`${name}: SVG payload cannot be decoded`)
    }
    const content = svg.replace(/^<\?xml\s[^>]*\?>\s*/i, '')
    if (!/^<svg\s[^>]*>[\s\S]*<\/svg>$/i.test(content)) throw new Error(`${name}: SVG must have one svg root with a closing tag`)
    if (!/\bxmlns\s*=\s*['"]http:\/\/www\.w3\.org\/2000\/svg['"]/.test(content)) throw new Error(`${name}: SVG namespace missing`)
    if (!/\bviewBox\s*=\s*['"][^'"]+['"]/.test(content)) throw new Error(`${name}: SVG viewBox missing`)
    if (!/<(?:path|circle|rect|ellipse|line|polyline|polygon|g)\b/i.test(content)) throw new Error(`${name}: SVG contains no visible geometry`)
    if (/<(?:script|foreignObject)\b|\bonload\s*=/i.test(content)) throw new Error(`${name}: SVG contains disallowed active content`)
    icons.set(name, { uri, svg: content })
  }
  return icons
}

module.exports = { inspectMaskRegistry }
