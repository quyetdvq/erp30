# MEDIA-018A — Year-based Product + Avatar Images

## Ownership and scope

- `Infrastructure/Media/YearlyImageStorage` owns MIME validation, GD decode/re-encode, proportional max 2048x2048 resize, filename collision-safe exclusive writes, and validated paths. Product and Identity keep the existing authorized upload/remove flows and metadata CAS/cleanup.
- Stored *new* filename: `YYYYMMDD{product|avatar}{last seven Unix-seconds}.jpg|png|webp`; physical path: `backend/writable/uploads/YYYY/filename`. The year is derived only from the filename. Two uploads in the same second probe subsequent timestamp-derived seven-digit identifiers without overwrite.
- Product keeps `image_filename`; Shield user keeps `avatar_filename`. Legacy nullable `image_mime` / `image_size_bytes` DB columns are retained for backward compatibility but new Product writes clear them. No schema migration or new tables.
- Product responses expose `image_available` and optional `image_url`; Auth/Security User responses expose `has_avatar` and optional `avatar_url`. The API deployment currently serves new images at `/writable/uploads/YYYY/filename` (MEDIA-018B.3); **only valid new names** yield URLs. The previously planned `/uploads/YYYY/filename` remains a supported frontend compatibility URL when an alias is configured. Physical paths and raw names are not exposed.
- MEDIA-018B retires both authenticated image binary GET endpoints and all browser Blob fallbacks. Product POST/DELETE and Avatar PUT/DELETE remain PBAC protected. HTTP image bytes are handled only by the web server's static image URL (currently `/writable/uploads/YYYY/filename`; a restricted `/uploads` alias is preferred long-term) (no per-image PHP/SQL when served by the web server directly).

## Deployment: web-server configuration is REQUIRED

**Never publish all of `backend/writable`.** The API host currently serves the `/writable/uploads/YYYY/filename` subtree, as independently observed in a browser. Ensure that *only validated image filenames* under that subtree are public, and deny `writable/` logs, sessions, cache, backups and configuration. A separate restricted `/uploads/` alias is preferred for long-term hardening. **Static URL means public, not PBAC-protected:** anyone possessing a URL can fetch that image without logging in, including users with Product View denied. Confirm that Product images and avatar images are approved as public before enabling this rule. If images must remain private, do not deploy public URL serving without a separate access-control design; MEDIA-018B does not provide one.

### Apache 2.4 / XAMPP example (inside API site's VirtualHost)

Adjust the absolute filesystem path. `AliasMatch` is deliberately restricted to two known types and three image extensions; it does *not* expose the legacy folders or arbitrary `writable` files.

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

Ensure no other `Alias /uploads` or web-root symlink exposes broader paths. Force only three static MIME types via the web-server configuration. Disable PHP execution throughout `uploads`, not just at the application router.

### Nginx example (inside API site's server block)

```nginx
location ~ ^/uploads/(20[0-9]{2})/(20[0-9]{6}(?:product|avatar)[0-9]{7}\.(?:jpg|png|webp))$ {
    alias /var/www/dinkerp/backend/writable/uploads/$1/$2;
    add_header X-Content-Type-Options nosniff always;
    add_header Cache-Control "public, max-age=3600" always;
    types { image/jpeg jpg; image/png png; image/webp webp; }
    default_type application/octet-stream;
}
location /uploads/ { return 404; }
```

Static images are named immutably: replacing uploads creates a different URL. Previously fetched deleted images may still exist in browser caches for up to one hour. Do not enable static caching for images that require immediate revocation.

## Existing images (no silent migration)

Existing 40-hex files have no year encoded. They will NOT display after MEDIA-018B until migrated. Keep the old files intact for rollback/migration, not for image-serving requests. A controlled command can convert/re-encode and update only the filename using CAS, keeping the original bytes for rollback:

```powershell
cd backend
php spark images:migrate   # DRY RUN; lists missing legacy files, does not write
# Back up both erp database and backend/writable/uploads FIRST.
php spark images:migrate --apply --backup-confirmed --database=erp
```

Replace `erp` with the exact DB selected by CodeIgniter. Inspect the dry-run results and DB target before invoking apply. The command never deletes original files. Re-run dry-run to confirm zero pending legacy paths. Only perform any obsolete-file cleanup after you have independently verified migration and backup integrity. Never point a public alias at `product-images` or `user-avatars`.

## Runtime acceptance checks

- GD PHP extension with JPEG, PNG and WebP support is required to upload; CodeIgniter does not resize on frontend.
- For each type: 4000x3000 -> 2048x1536, 3000x4000 -> 1536x2048, 1200x800 unchanged, PNG transparency preserved, invalid/SVG/GIF/animated files rejected, EXIF removed by re-encoding, duplicate second names do not overwrite.
- Verify upload + authorized metadata write, replace & delete, rollback on metadata failure, session and PBAC denial, and direct static GET (200, expected Content-Type, no cookie) vs. invalid/legacy URL (404).
- PHP test lanes: `test-media-yearly-source.php`, UNIT-STANDARD and NO-DROP; frontend: type-check, existing Product/Avatar tests, Vite build.
- This development package has no `vendor`, PHP GD, or frontend dependencies, so local static/source tests do not certify GD/MariaDB/Shield or production web-server rules.
