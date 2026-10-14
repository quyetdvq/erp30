#!/usr/bin/env python3
"""Validate the source-derived ERP30 as-built documentation bundle.

This is structural/static QA only. It does not run CI4, a database, or browsers.
Run after `python tools/docs/rebuild_asbuilt_catalogs.py` from the repo root.
"""
from __future__ import annotations

import hashlib
import json
from pathlib import Path
import re
import sys
from urllib.parse import unquote

ROOT = Path(__file__).resolve().parents[2]
DOC = ROOT / "docs/as-built"
REF = DOC / "reference"
MANIFEST = REF / "inventory.json"
MANDATORY = [
    "README.md", "00_SCOPE_AND_BASELINE.md", "01_FUNCTIONAL_AS_BUILT.md",
    "02_DATA_ARCHITECTURE.md", "03_IMPLEMENTATION_ARCHITECTURE.md",
    "04_EVOLUTION_RULES.md", "05_API_CONTRACT.md", "06_FRONTEND_UI.md",
    "07_IAM_AND_WORKFLOW.md", "08_RUNTIME_OPERATIONS.md",
    "09_VERIFICATION_MATRIX.md", "10_P1_DRIFT_REGISTER.md", "11_TRACEABILITY.md", "12_FRONTEND_REFACTOR_V1.md", "13_FRONTEND_REFACTOR_V1_1_HOTFIX.md", "14_FRONTEND_REFACTOR_V1_2_VALIDATION.md", "15_FRONTEND_V2_START_BASELINE.md", "16_FRONTEND_V2_1_APPSHELL_REFACTOR.md",
    "reference/API_ENDPOINTS.md", "reference/DATABASE_SCHEMA.md",
    "reference/FRONTEND_ROUTES.md", "reference/TEST_FILES.md", "reference/inventory.json",
]
errors: list[str] = []
link_count = 0


def err(message: str) -> None:
    errors.append(message)


for rel in MANDATORY:
    if not (DOC / rel).is_file():
        err("Missing document: " + rel)

# URLs inside Markdown link syntax; links to anchors/source code are checked
# for filename presence, but heading resolution needs a Markdown renderer.
md_links = re.compile(r"(?<!!)\[[^\]]+\]\(([^)]*)\)")
for md in sorted(DOC.rglob("*.md")):
    content = md.read_text(encoding="utf-8")
    for line_num, line in enumerate(content.splitlines(), 1):
        for match in md_links.finditer(line):
            link_count += 1
            link = match.group(1).split("#", 1)[0]
            if not link or link.startswith(("https://", "http://", "mailto:", "data:")):
                continue
            target = md.parent / unquote(link)
            if not target.exists():
                err(f"Bad relative link: {md.relative_to(ROOT)}:{line_num} -> {link}")

if MANIFEST.exists():
    data = json.loads(MANIFEST.read_text(encoding="utf-8"))
    metrics = data["metrics"]
    for json_name, src_name in (
        ("routes_file_sha256", "backend/app/Config/Routes.php"),
        ("openapi_file_sha256", "contracts/openapi/openapi.yaml"),
        ("router_file_sha256", "frontend/src/router/routes.ts"),
        ("router_entry_file_sha256", "frontend/src/router/index.ts"),
        ("navigation_file_sha256", "frontend/src/app/navigation.ts"),
    ):
        actual = hashlib.sha256((ROOT / src_name).read_bytes()).hexdigest()
        if actual != data[json_name]:
            err(f"Stale source fingerprint: {src_name}; rebuild catalogue")
    migration_dir = ROOT / "backend/app/Database/Migrations"
    migration_files = {p.name for p in migration_dir.glob("*.php")}
    recorded_files = set(data["migration_file_sha256"])
    if migration_files != recorded_files:
        err("Migration file list changed; update/rebuild schema scanner")
    for name, expected_hash in data["migration_file_sha256"].items():
        actual_hash = hashlib.sha256((migration_dir / name).read_bytes()).hexdigest()
        if expected_hash != actual_hash:
            err("Stale migration fingerprint: " + name)
    tests = data["test_files"]
    for group, paths in tests.items():
        for path in paths:
            if not (ROOT / path).is_file():
                err(f"Missing test file in inventory ({group}): {path}")
    rts = data["routes"]
    ops = data["openapi_operations"]
    if metrics["api_routes_including_options"] != len(rts):
        err("Route count mismatch")
    if metrics["openapi_operations"] != len(ops):
        err("OpenAPI count mismatch")
    if metrics["frontend_named_routes"] != len(data["frontend_routes"]):
        err("UI route count mismatch")
    if metrics["application_tables"] != len(data["schema"]):
        err("Schema table count mismatch")
    if metrics["local_migrations"] != len(data["migrations"]):
        err("Migrations count mismatch")
    if metrics["application_columns_after_000017"] != sum(len(row["columns"]) for row in data["schema"].values()):
        err("Schema columns count mismatch")
    if metrics["declared_foreign_keys"] != sum(len(row["foreign_keys"]) for row in data["schema"].values()):
        err("Schema FK count mismatch")
    for rt in data["frontend_routes"]:
        if not (ROOT / "frontend/src" / rt["view"]).is_file():
            err("Vue view missing: " + rt["view"])
    def signature(route: dict) -> tuple[str, str]:
        path = re.sub(r"\{[^}]+\}|\(:[^)]+\)", "{param}", route["path"])
        return route["method"], path
    route_set = {signature(rt) for rt in rts if rt["method"] != "OPTIONS"}
    api_set = {signature(op) for op in ops}
    if route_set != api_set:
        err("OpenAPI/backend non-OPTIONS route signature mismatch")
    if len(rts) - len(route_set) != 3:
        err("Unexpected number of OPTIONS/prefight route entries")

print(json.dumps({
    "status": "PASS_STATIC" if not errors else "FAIL_STATIC",
    "asbuilt_markdown_files": len(list(DOC.rglob("*.md"))),
    "checked_markdown_links": link_count,
    "errors": errors,
}, indent=2, ensure_ascii=False))
sys.exit(1 if errors else 0)
