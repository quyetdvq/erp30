import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { strict as assert } from 'node:assert'

const view = readFileSync(fileURLToPath(new URL('../pages/ProductsView.vue', import.meta.url)), 'utf8')
const thumbnail = readFileSync(fileURLToPath(new URL('../../../components/products/ProductListThumbnail.vue', import.meta.url)), 'utf8')

test('Product table keeps nine cells and puts full name before metadata', () => {
  const header = view.split('<thead>')[1]?.split('</thead>')[0]
  assert.ok(header)
  const cells = [...header.matchAll(/<th\b[^>]*>([\s\S]*?)<\/th>/g)].map((match) => match[1])
  assert.equal(cells.length, 9)
  assert.match(cells[0], /t\('product\.imageColumn'\)/)
  assert.match(cells[1], /t\('product\.fullName'\)/)
  assert.match(cells[2], /t\('product\.information'\)/)
  assert.match(cells[3], /t\('product\.groupColumn'\)/)

  const body = view.split('<tbody>')[1]?.split('</tbody>')[0]
  assert.ok(body)
  const imageAt = body.indexOf('<td class="product-image-column">')
  const nameAt = body.indexOf('<td class="product-name-column">')
  const infoAt = body.indexOf('<td class="product-information-column">')
  const groupAt = body.indexOf('<td class="product-group-column">')
  assert.ok(imageAt >= 0 && imageAt < nameAt && nameAt < infoAt && infoAt < groupAt)
  // The child expansion control is under Full name, not below the thumbnail.
  const moreRow = body.split('<tr v-else class="product-child-control-row">')[1]?.split('</tr>')[0]
  assert.ok(moreRow)
  assert.match(moreRow, /<td class="product-image-column"><\/td>/)
  assert.match(moreRow, /<td colspan="8" class="product-child-control-cell">/)
  assert.match(view, /<td class="table-actions">/)
  assert.match(view, /display: table-cell;/)
  assert.doesNotMatch(view, /\.table-actions\s*\{\s*display:\s*flex/)
})

test('Full name owns tree actions and child indentation; Info still has three plain lines', () => {
  const name = view.split('<td class="product-name-column">')[1]?.split('</td>')[0]
  assert.ok(name)
  assert.match(name, /product-name-cell--child/)
  assert.match(name, /class="product-tree-toggle"/)
  assert.match(name, /:aria-expanded="!collapsedChildren\.has/)
  assert.match(name, /\$\{productName\(entry\.product\)\}/)
  assert.match(name, /class="product-tree-branch" aria-hidden="true"/)
  assert.match(name, /class="product-link"/)
  assert.match(name, /ProductRelationSummary/)
  assert.ok(name.indexOf('class="product-tree-toggle"') < name.indexOf('class="product-link"'))

  const info = view.split('<td class="product-information-column">')[1]?.split('</td>')[0]
  assert.ok(info)
  for (const field of ['product.code', 'product.sku', 'product.type']) {
    assert.ok(info.includes(`t('${field}')`), field)
  }
  assert.doesNotMatch(info, /product-tree-toggle|product-tree-branch|product-tree-code/)

  const group = view.split('<td class="product-group-column">')[1]?.split('</td>')[0]
  assert.ok(group)
  assert.match(group, /groupName\(entry\.product\.product_group_id\)/)
  assert.match(group, /productRoleLabel\(entry\.product\.role, t\)/)
  assert.match(view, /:show-edit="entry\.product\.allowed_actions\?\.edit === true"/)
  assert.match(view, /:show-toggle="entry\.product\.allowed_actions\?\.setstate === true"/)
})

test('Visual tree cues remain inside table cells, preserve focus and status', () => {
  assert.match(view, /\.product-name-cell--child\s*\{\s*padding-left:/)
  assert.match(view, /\.product-tree-branch\s*\{[\s\S]*?border-left:[\s\S]*?border-bottom:/)
  assert.match(view, /\.product-child-row\s*\{\s*background:/)
  assert.match(view, /\.product-child-row td\.product-name-column\s*\{\s*box-shadow:/)
  assert.match(view, /\.product-tree-toggle:focus-visible/)
  assert.match(view, /\.product-link:focus-visible/)
  assert.match(view, /\.master-table tbody tr\.product-data-row:focus-within/)
  assert.match(view, /<TableStatus :status="entry\.product\.status" \/>/)
  assert.match(view, /:total="state\.total\.value"/)
})

test('image column uses only static URLs, lazy loading and a safe placeholder', () => {
  assert.match(view, /<ProductListThumbnail/)
  assert.match(view, /:image-available="entry\.product\.image_available === true"/)
  assert.match(view, /:image-path="entry\.product\.image_url"/)
  assert.match(thumbnail, /uploadImageUrl\(props\.imagePath, 'product'\)/)
  assert.match(thumbnail, /visible\.value && auth\.user && !failed\.value/)
  assert.match(thumbnail, /IntersectionObserver/)
  assert.match(thumbnail, /:src="imageUrl"/)
  assert.match(thumbnail, /@error="failed = true"/)
  assert.doesNotMatch(thumbnail, /fetchProductImage|createObjectURL|responseType|blob:/)
  assert.doesNotMatch(thumbnail.split('<template>')[1], /<img[^>]*src="\/api/)
})
