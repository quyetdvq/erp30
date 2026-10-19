DinkERP Product Media PM-0 - XAMPP backend overlay v2
Target directory: D:\XAMPP\htdocs\erp\backend

This v2 fixes a false preflight block caused by hashing documentation files such as backend\README.md.
The baseline gate now hashes only Product Media behavior/configuration files used by PM-0.
No application/business implementation is changed by this overlay.

Install:
1) Extract this ZIP directly into D:\XAMPP\htdocs\erp\backend and overwrite the PM-0 test/tooling files from v1.
2) Run:
   cd D:\XAMPP\htdocs\erp\backend
   php tools\test-product-media-gates.php
   php tools\test-product-media.php --preflight

If preflight reports another "Baseline mismatch: <path>", do not force it. Send that exact path/output for review.
