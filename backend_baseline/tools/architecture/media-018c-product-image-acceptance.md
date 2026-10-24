# MEDIA-018B.2 — Product image save and static URL acceptance

This incremental frontend-only fix follows MEDIA-018B.1. It does not restore the retired image GET controllers, create new media services, change SQL schema or bypass PBAC.

## Diagnosis

Two independent failures can appear together:

1. **New Product image not saved:** `ProductImageField` used one Vue watcher on `productId`, `imageAvailable` and `imagePath`. After `POST /api/v1/products`, the newly assigned API metadata could change `imagePath` from `undefined` to `null` and erase `selectedFile` before `POST /api/v1/products/{id}/image`. The editor now commits the pending file before installing new metadata; changes to existing-record image metadata no longer discard a pending upload. A successful CREATE followed by an unsuccessful image POST redirects to the saved Product's EDIT route and preserves the pending image for retry instead of posting a duplicate Product. Product mutations retain existing PBAC.
2. **Stored image not displayed:** MEDIA-018B intentionally removed all PHP/SQL image GET fallbacks. A valid filename alone is not enough: the Product API must return a non-null `image_url`, and the API origin's **web server** must map the returned `/writable/uploads/YYYY/filename` to the real file. The Product thumbnail now uses a validated `image_url` without a second `image_available` gate, but the image will remain a placeholder if Apache does not serve that URL.

## Check the actual HTTP flow

In the browser DevTools > Network > All (Disable cache), create or edit a Product, choose a JPEG/PNG/WebP image (<= 3 MiB), then click Save. Expected sequence:

- New Product: `POST /api/v1/products` returns Product with `id`.
- Image: `POST /api/v1/products/{id}/image` submits **multipart/form-data**, field name `image`, and returns Product with `image_url`. The Product Editor uses this response directly (no redundant metadata GET).
- A separate authorized `GET /api/v1/products/{id}` **if requested later** should also return `image_url: "/writable/uploads/YYYY/YYYYMMDDproduct#######.jpg"` (appropriate extension).
- Browser `GET https://API_ORIGIN/writable/uploads/YYYY/YYYYMMDDproduct#######.jpg` returns `200` and **image bytes**, not HTML.

Existing Product updates use `PATCH /api/v1/products/{id}` instead of CREATE. If the image POST is `403`, check Product EDIT PBAC; `413` indicates web-server upload limits, and `422` indicates upload/MIME/content validation. These are distinct from the prior frontend loss of `selectedFile`. Never disable PBAC to work around an error.

### Distinguish metadata problems from static-server problems

1. Inspect the authorized Product API JSON, not only the DB filename. If `image_url` is `null`, validate that the DB filename exactly matches `YYYYMMDDproduct{7 digits}.jpg|png|webp`, with a real calendar date; upload `20261023product5689522.jpg` => `/writable/uploads/2026/20261023product5689522.jpg`. Old filenames are **not** served.
2. If `image_url` is present, copy it from the API response and request the complete URL on the **API origin** (not the frontend Vite origin). For example in PowerShell:

   ```powershell
   curl.exe -sS -L -o NUL -w "HTTP=%{http_code} TYPE=%{content_type} FINAL=%{url_effective}`n" "https://YOUR_API_HOST/writable/uploads/2026/20261023product5689522.jpg"
   ```

   `HTTP=200 TYPE=image/jpeg` (or image/png/webp) is required. A `200 text/html` response might be a SPA fallback; `404` usually means the static mapping is absent/wrong; `403` commonly means the alias directory is denied. Check the real **Request URL** under Network rather than assuming the API host from the UI.
3. On Windows, check the same file exists on disk at `D:\XAMPP\htdocs\erp\backend\writable\uploads\2026\20261023product5689522.jpg`.

## Apache/XAMPP — static mapping for API VirtualHost

Place this block in the VirtualHost that responds to the exact host in the failed image Request URL (e.g. the site's `httpd-vhosts.conf`, NOT in frontend Vue code). Adapt the path if XAMPP uses another root; confirm `mod_alias` is loaded. Do not add a generic `/writable` alias.

```apache
AliasMatch "^/uploads/(20[0-9]{2})/(20[0-9]{6}(product|avatar)[0-9]{7}\.(jpg|png|webp))$" "D:/XAMPP/htdocs/erp/backend/writable/uploads/$1/$2"
<Directory "D:/XAMPP/htdocs/erp/backend/writable/uploads">
    Options -Indexes -ExecCGI -Includes -FollowSymLinks
    AllowOverride None
    Require all granted
    <FilesMatch "\.(?:jpg|png|webp)$">
        SetHandler none
    </FilesMatch>
    <IfModule mod_headers.c>
        Header always set X-Content-Type-Options "nosniff"
        Header set Cache-Control "public, max-age=3600"
    </IfModule>
</Directory>
```

Run `httpd.exe -t` (in XAMPP Apache `bin`) or `apachectl -t` on Linux, then restart Apache. Re-run the same full image URL in an unauthenticated/private browser window. The image GET must work **without** cookies and **without** a PHP handler or DB connection. If the API host is remote or proxied (e.g. `api.monmin.net`), configure **that origin's** server, not an unrelated local Apache VirtualHost.

**Security:** This makes Product images and avatars publicly retrievable by anyone with their URL. It does not alter existing PBAC checks for upload/replacement/deletion. Public URLs must not expose the rest of `writable`.

## Verification commands (repo root)

```powershell
node --test frontend/src/features/catalog/__tests__/productImageSaveFlow.node.mjs
node --test frontend/src/features/catalog/__tests__/productListLayout.node.mjs
node --experimental-strip-types --test frontend/src/features/catalog/__tests__/productInlineRows.node.mjs
php backend/tools/test-media-static-only.php
cd frontend
pnpm run type-check
pnpm exec vitest run src/components/products/__tests__/ProductImageField.spec.ts src/components/products/__tests__/ProductListThumbnail.spec.ts src/components/users/__tests__/UserAvatar.spec.ts
pnpm run build-only
```

Source tests do not validate the live Apache site or runtime permissions. Verify GET and upload POST status in the Network panel to finish acceptance.

## MEDIA-018B.3 runtime path correction

The API host was verified in the browser serving `https://api.monmin.net/writable/uploads/2026/20261023product2726226.jpg`, not the canonical `/uploads` alias. Product and Avatar responses now emit the observed prefix, while frontend validation accepts both prefixes during the transition. No image binary API is reintroduced. For safe future deployment, map the cleaner `/uploads/` URL to the same image subtree, then switch the generator back and deny requests to other `/writable/` paths.
