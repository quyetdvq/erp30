#!/usr/bin/env python3
"""Rebuild static as-built catalogues from the checked-out ERP30 code.

Requirements: Python >= 3.10 and PyYAML (`python -m pip install pyyaml`).
Static scanner only; no database access, HTTP requests or code execution.
Do not interpret successful static extraction as behavioral validation.
"""
from __future__ import annotations

from collections import Counter, defaultdict
from datetime import date
import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys

try:
    import yaml
except ImportError:
    sys.exit("PyYAML is required: python -m pip install pyyaml")

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "docs/as-built/reference"
OUT.mkdir(parents=True, exist_ok=True)
ROUTES_FILE = ROOT / "backend/app/Config/Routes.php"
OPENAPI_FILE = ROOT / "contracts/openapi/openapi.yaml"
UI_FILE = ROOT / "frontend/src/router/routes.ts"
ROUTER_ENTRY_FILE = ROOT / "frontend/src/router/index.ts"
NAV_FILE = ROOT / "frontend/src/app/navigation.ts"
SRC_LINES = ROUTES_FILE.read_text(encoding="utf-8").splitlines()


def md_ref(rel: str, line: int | None = None, depth: int = 3) -> str:
    return f"{'../' * depth}{rel}" + (f"#L{line}" if line else "")


def format_type(raw: str | None) -> str:
    if raw is None:
        return "?"
    return raw.strip("'\"")


def token_attr(body: str, key: str) -> str | None:
    match = re.search(r"'" + re.escape(key) + r"'\s*=>\s*('(?:[^']*)'|\"(?:[^\"]*)\"|[^,\n]+)", body)
    return match.group(1).strip() if match else None


def schema_catalog() -> tuple[dict, list]:
    schema: dict[str, dict] = {}
    migrations: list[dict] = []
    for file in sorted((ROOT / "backend/app/Database/Migrations").glob("*.php")):
        content = file.read_text(encoding="utf-8")
        created = []
        # Initial CREATE statements only. Subsequent incremental migrations are
        # explicitly applied below; this is NOT a replacement for migrating DB.
        pattern = re.compile(r"\$this->forge->addField\(\[\n(.*?)\n\s*\]\);\s*(.*?)\$this->forge->createTable\(\s*'([^']+)'", re.S)
        for match in pattern.finditer(content):
            block, commands, table = match.groups()
            starts = list(re.finditer(r"(?m)^ {12}'([^']+)'\s*=>\s*\[", block))
            cols = []
            for index, start in enumerate(starts):
                attr_str = block[start.end():starts[index + 1].start() if index + 1 < len(starts) else len(block)]
                raw_type = token_attr(attr_str, "type")
                raw_constraint = token_attr(attr_str, "constraint")
                raw_null = token_attr(attr_str, "null")
                cols.append({
                    "name": start.group(1), "type": format_type(raw_type),
                    "constraint": format_type(raw_constraint) if raw_constraint else None,
                    "nullable": (raw_null == "true") if raw_null is not None else None,
                    "default": token_attr(attr_str, "default"),
                    "auto_increment": token_attr(attr_str, "auto_increment") == "true",
                })
            foreign_keys = [list(m.groups()) for m in re.finditer(
                r"\$this->forge->addForeignKey\('([^']+)',\s*'([^']+)',\s*'([^']+)'", commands
            )]
            uniques = [m.group(1).strip() for m in re.finditer(r"\$this->forge->addUniqueKey\(([^;]+)\);", commands)]
            schema[table] = {
                "source": str(file.relative_to(ROOT)), "source_line": content[:match.start()].count("\n") + 1,
                "columns": cols, "foreign_keys": foreign_keys, "unique_keys": uniques,
            }
            created.append(table)
        migrations.append({"file": str(file.relative_to(ROOT)), "creates": created})

    # Deliberately refuse unknown new migrations. Incremental changes below are
    # explicitly modeled; silently counting them without applying them would
    # manufacture a false as-built schema.
    assert len(migrations) == 17 and Path(migrations[-1]["file"]).name == "2026-10-12-000017_AddProductRepresentativeImage.php", (
        "Migration ledger changed. Extend schema_catalog() to model new DDL and data effects."
    )
    # Apply incremental changes (000009..000017) in sequence, derived from code.
    # The data migrations 000013 / 000015 affect seed/auth rows, not new tables.
    assert len(schema) == 31, f"Expected 31 application tables, got {len(schema)}. Update scanner for schema changes."
    schema["product_groups"]["columns"].append({
        "name": "parent_id", "type": "BIGINT", "constraint": None,
        "nullable": True, "default": None, "auto_increment": False, "change": "000009",
    })
    schema["product_groups"]["foreign_keys"].append(["parent_id", "product_groups", "id"])
    schema["policies"]["columns"].append({
        "name": "normalized_state_id", "type": "BIGINT UNSIGNED GENERATED AS COALESCE(state_id,0) PERSISTENT",
        "constraint": None, "nullable": False, "default": None, "auto_increment": False, "change": "000012",
    })
    schema["policies"]["unique_keys"].append("uq_policies_scope (organization_id,resource_id,normalized_state_id,participant_type,participant_ref_id) [000012]")
    for table in ("policies", "resources"):
        schema[table]["columns"].append({
            "name": "status", "type": "VARCHAR", "constraint": "20", "nullable": False,
            "default": "'ACTIVE'", "auto_increment": False, "change": "000014",
        })
    for table in ("provinces", "wards", "organizations", "contacts", "departments", "positions", "employees", "product_groups", "product_uoms", "products", "price_lists"):
        for field in schema[table]["columns"]:
            if field["name"] == "status":
                field["constraint"] = "50"
                field["change"] = "000016"
    for field in schema["resources"]["columns"]:
        if field["name"] == "domain":
            field["nullable"] = False
            field["change"] = "000010"
    for name, typ, constraint in (("image_filename", "VARCHAR", "80"), ("image_mime", "VARCHAR", "32"), ("image_size_bytes", "INT UNSIGNED", None)):
        schema["products"]["columns"].append({
            "name": name, "type": typ, "constraint": constraint, "nullable": True,
            "default": None, "auto_increment": False, "change": "000017",
        })
    return schema, migrations


def get_routes() -> list[dict]:
    routes = []
    def add(method: str, path: str, handler: str, line: int, generated: bool = False):
        routes.append({"method": method.upper(), "path": "/api/v1/" + path, "handler": handler, "line": line, "loop_expansion": generated})
    for line_number, line in enumerate(SRC_LINES, 1):
        # foreach loop entries use $path, not a quoted literal; we explicitly
        # expand those below. Do not rely on hard-coded excluded line ranges.
        for found in re.finditer(r"\$routes->(get|post|put|patch|delete|options)\('([^']+)'\s*,\s*'([^']+)'\)", line):
            if line_number != 6:  # Root Home route lives outside /api/v1.
                add(*found.groups(), line_number)
    for mapping, handler, line_numbers in (
        ({"provinces": "province", "wards": "ward", "organizations": "organization", "contacts": "contact"}, "FoundationOrganizationContact", (27, 28, 29, 30, 31)),
        ({"departments": "department", "positions": "position", "employees": "employee"}, "EmployeeStructure", (34, 35, 36, 37, 38)),
    ):
        for path, resource in mapping.items():
            for method, suffix, action, source_line in zip(
                ["get", "post", "get", "patch", "post"],
                ["", "", "/(:num)", "/(:num)", "/(:num)/actions/set-state"],
                ["list", "create", "show", "update", "setState"], line_numbers,
            ):
                add(method, path + suffix, f"Api\\{handler}::{action}/{resource}", source_line, True)
    for path, resource in {
        "authorization-groups": "authorization_group", "resources": "resource",
        "policies": "policy", "authorization-logs": "authorization_log",
        "workflow-states": "workflow_state", "lifecycles": "lifecycle",
    }.items():
        prefix = "Api\\AuthorizationWorkflow::"
        add("get", path, prefix + "list/" + resource, 57, True)
        if resource != "authorization_log":
            add("post", path, prefix + "create/" + resource, 59, True)
        add("get", path + "/(:num)", prefix + "show/" + resource, 61, True)
        if resource != "authorization_log":
            add("patch", path + "/(:num)", prefix + "update/" + resource, 63, True)
            if resource == "policy":
                add("delete", path + "/(:num)", prefix + "deletePolicy", 65, True)
        if resource in ("authorization_group", "workflow_state", "lifecycle", "policy", "resource"):
            add("post", path + "/(:num)/actions/set-state", prefix + "setState/" + resource, 69, True)
    unique = set()
    for r in routes:
        k = (r["method"], r["path"])
        assert k not in unique, f"Duplicate backend route: {k}"
        unique.add(k)
    return sorted(routes, key=lambda d: (d["path"], d["method"]))


def route_signature(path: str) -> str:
    return re.sub(r"\{[^}]+\}|\(:[^)]+\)", "{param}", path)


def get_openapi() -> tuple[dict, list[dict]]:
    spec = yaml.safe_load(OPENAPI_FILE.read_text(encoding="utf-8"))
    result = []
    for path, methods in spec["paths"].items():
        for method, data in methods.items():
            if method.upper() in {"GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"}:
                result.append({"method": method.upper(), "path": path, "operation_id": data.get("operationId", ""), "summary": data.get("summary", "")})
    return spec, result


def get_ui_routes() -> list[dict]:
    lines = UI_FILE.read_text(encoding="utf-8").splitlines()
    routes = []
    for line_no, line in enumerate(lines, 1):
        found = re.search(r"\bpath:\s*'([^']*)'", line)
        if not found:
            continue
        path = found.group(1)
        # Root shell parent does not have its own named screen.
        if path == "/":
            continue
        item = "\n".join(lines[line_no-1:line_no+11])
        matched_name = re.search(r"name:\s*'([^']+)'", item)
        matched_component = re.search(r"import\('@/((?:features|app)/[^']+\.vue)'\)", item)
        if not matched_name or not matched_component:
            continue
        full_path = path if path.startswith("/") else "/" + path
        routes.append({"path": full_path, "name": matched_name.group(1), "view": matched_component.group(1), "line": line_no})
    # Read AppShell navigation permissions as presentation hints, NOT backend access rules.
    nav_lines = NAV_FILE.read_text(encoding="utf-8").splitlines()
    nav_map = {}
    in_nav = False
    for number, line in enumerate(nav_lines):
        if line.startswith("export const navItems:"):
            in_nav = True
        if line.startswith("// Detail and self-service"):
            in_nav = False
        if not in_nav:
            continue
        found = re.search(r"\bto:\s*'([^']+)'", line)
        if found:
            candidate = None
            for nxt in nav_lines[number:number+9]:
                if nxt != line and re.search(r"\bto:\s*'", nxt):
                    break
                permission = re.search(r"permission:\s*'([^']+)'", nxt)
                if permission:
                    candidate = permission.group(1)
                    break
                if "}," in nxt and nxt != line:
                    break
            if candidate or found.group(1) not in nav_map:
                nav_map[found.group(1)] = candidate
    for item in routes:
        item["menu_permission"] = nav_map.get(item["path"])
        item["in_menu"] = item["path"] in nav_map
    return routes


def list_tests() -> dict[str, list[str]]:
    return {
        "phpunit": sorted(str(x.relative_to(ROOT)) for x in (ROOT/"backend/tests").rglob("*Test.php")),
        "vitest": sorted(str(x.relative_to(ROOT)) for x in (ROOT/"frontend/src").rglob("*.spec.ts")),
        "playwright": sorted(str(x.relative_to(ROOT)) for x in (ROOT/"frontend/e2e").rglob("*.spec.ts")),
    }


def md_api(routes: list[dict], ops: list[dict], missing: list[dict], extra: list[dict]) -> str:
    index = {(o["method"], route_signature(o["path"])):o for o in ops}
    rows = [
        "# Danh mục HTTP endpoint as-built (tự động sinh)",
        "", "> Generated by `tools/docs/rebuild_asbuilt_catalogs.py`; read-only scan of `Routes.php` and `openapi.yaml`.",
        "", f"- Backend route operations: **{len(routes)}** (bao gồm {sum(x['method']=='OPTIONS' for x in routes)} `OPTIONS`).",
        f"- OpenAPI operations: **{len(ops)}** trên **{len(set(o['path'] for o in ops))}** paths.",
        f"- Route không mô tả OpenAPI: **{len(missing)}** (tại baseline chỉ gồm CORS `OPTIONS`). OpenAPI-only: **{len(extra)}**.",
        "- Khớp ở cấp `method + path shape`, KHÔNG chứng minh schema request/response hay hành vi runtime khớp.",
        "", "| HTTP | Backend route | Handler | OpenAPI operationId | Source |", "|---|---|---|---|---|",
    ]
    for route in routes:
        op = index.get((route["method"],route_signature(route["path"])))
        uri = (op["path"] if op else route["path"]).replace("|", "\\|")
        source = md_ref("backend/app/Config/Routes.php",route["line"])
        rows.append(f"| {route['method']} | `{uri}` | `{route['handler']}` | `{op['operation_id'] if op else '—'}` | [L{route['line']}]({source}) |")
    rows += ["", "## Contract mismatch hiện được phát hiện bằng path shape", ""]
    rows += [f"- Missing OpenAPI: `{r['method']} {r['path']}`" for r in missing] or ["- Không có."]
    rows += [f"- OpenAPI-only: `{r['method']} {r['path']}`" for r in extra] or ["- Không có."]
    return "\n".join(rows) + "\n"


def md_db(schema: dict, migrations: list[dict]) -> str:
    rows = [
        "# Database schema as-built (trích xuất tĩnh từ migration)", "",
        "> Generated by `tools/docs/rebuild_asbuilt_catalogs.py`; chỉ phản ánh code migration trong repository, không phải introspection database đang chạy.",
        "", f"- **{len(schema)}** bảng do ứng dụng định nghĩa; **{sum(len(s['columns']) for s in schema.values())}** cột trong snapshot sau migration 000017; **{sum(len(s['foreign_keys']) for s in schema.values())}** khóa ngoại khai báo ở migration.",
        "- Không bao gồm bảng do CodeIgniter Shield/vendor định nghĩa (`users`, `auth_identities`, `auth_groups_users`, `auth_permissions_users`, ...). Kiểm tra migration vendor và DB chạy thực tế trước khi xây ERD triển khai.",
        "- Các thao tác DDL/DML tùy điều kiện ở migration 000012–000015 cần kiểm tra dữ liệu runtime; scanner không chứng nhận migration đã chạy thành công.",
        "", "## Ledger migrations", "", "| Migration | Bảng tạo mới |", "|---|---|",
    ]
    for m in migrations:
        p = m["file"]
        rows.append(f"| [`{Path(p).name}`]({md_ref(p)}) | {', '.join('`'+t+'`' for t in m['creates']) or '— (schema/data correction)'} |")
    rows += ["", "## Danh mục bảng và quan hệ", "", "| Table | Cột | FK | Nguồn tạo |", "|---|---:|---:|---|"]
    for name,data in schema.items():
        rows.append(f"| [`{name}`](#{name.replace('_','-')}) | {len(data['columns'])} | {len(data['foreign_keys'])} | [`{Path(data['source']).name}`]({md_ref(data['source'], data['source_line'])}) |")
    for name, data in schema.items():
        rows += ["", f"## {name}", "",f"Tạo bởi: [`{data['source']}`]({md_ref(data['source'],data['source_line'])}).", "", "| Column | Migration datatype (đã áp dụng bổ sung tĩnh) | Nullable | Default | Change |", "|---|---|---|---|---|"]
        for col in data["columns"]:
            typ = col["type"] + (f"({col['constraint']})" if col["constraint"] else "")
            if col["auto_increment"]:
                typ += " AUTO_INCREMENT"
            nullable = "YES" if col["nullable"] is True else ("NO" if col["nullable"] is False else "not explicit")
            default = (col["default"] or "—").replace("|", "\\|")
            rows.append(f"| `{col['name']}` | `{typ}` | {nullable} | `{default}` | {col.get('change','—')} |")
        rows += ["", "**Foreign keys:** " + ("; ".join(f"`{local}` → `{target}.{remote}`" for local,target,remote in data["foreign_keys"]) if data["foreign_keys"] else "none in local migration") + "."]
        rows += ["", "**Unique keys:** " + ("; ".join(f"`{key}`" for key in data["unique_keys"]) if data["unique_keys"] else "none in local migration") + "."]
    return "\n".join(rows) + "\n"


def md_ui(routes: list[dict]) -> str:
    rows = [
        "# Frontend route/view catalogue as-built (tự động sinh)", "",
        "> Scan từ `frontend/src/router/routes.ts` và menu `frontend/src/app/navigation.ts`.",
        "",f"- **{len(routes)}** named view routes (bao gồm `/login` và not-found).",
        "- `menu_permission` là hint ẩn/hiện menu. Không thay thế PBAC cấp record trong backend.",
        "", "| Route | Name | Vue view | Menu? | Menu Base Permission hint | Source |", "|---|---|---|---|---|---|",
    ]
    for r in routes:
        p = md_ref("frontend/src/router/routes.ts",r["line"])
        view = md_ref("frontend/src/" + r["view"])
        rows.append(f"| `{r['path']}` | `{r['name']}` | [`{Path(r['view']).name}`]({view}) | {'yes' if r['in_menu'] else 'no'} | `{r['menu_permission'] or '—'}` | [L{r['line']}]({p}) |")
    return "\n".join(rows) + "\n"


def md_tests(tests: dict[str,list[str]]) -> str:
    rows = ["# Test source catalogue (tự động sinh)", "", "> File presence chỉ nói lên phạm vi source; không phải kết quả test PASS.", ""]
    for group,files in tests.items():
        rows += [f"## {group} — {len(files)} files", ""]
        rows += [f"- [`{Path(path).name}`]({md_ref(path)})" for path in files]
        rows.append("")
    return "\n".join(rows) + "\n"


def sha256(file: Path) -> str:
    return hashlib.sha256(file.read_bytes()).hexdigest()


def main() -> None:
    schema, migrations = schema_catalog()
    routes = get_routes()
    spec, openapi = get_openapi()
    frontend = get_ui_routes()
    tests = list_tests()
    api_set = {(o["method"],route_signature(o["path"])) for o in openapi}
    route_set = {(r["method"],route_signature(r["path"])) for r in routes}
    missing = [r for r in routes if (r["method"],route_signature(r["path"])) not in api_set]
    extra = [o for o in openapi if (o["method"],route_signature(o["path"])) not in route_set]
    (OUT/"API_ENDPOINTS.md").write_text(md_api(routes,openapi,missing,extra),encoding="utf-8")
    (OUT/"DATABASE_SCHEMA.md").write_text(md_db(schema,migrations),encoding="utf-8")
    (OUT/"FRONTEND_ROUTES.md").write_text(md_ui(frontend),encoding="utf-8")
    (OUT/"TEST_FILES.md").write_text(md_tests(tests),encoding="utf-8")
    try:
        baseline_commit = subprocess.check_output(["git","-C",str(ROOT),"rev-parse","HEAD"],text=True,stderr=subprocess.DEVNULL).strip()
    except Exception:
        baseline_commit = "unavailable"
    manifest = {
        "document_class": "code-derived as-built static inventory",
        "generated_at_date": date.today().isoformat(),
        "workspace_git_head_at_generation": baseline_commit,
        "workspace_has_uncommitted_changes": subprocess.call(["git", "-C", str(ROOT), "diff", "--quiet"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL) != 0,
        "routes_file_sha256": sha256(ROUTES_FILE),
        "openapi_file_sha256": sha256(OPENAPI_FILE),
        "router_file_sha256": sha256(UI_FILE),
        "router_entry_file_sha256": sha256(ROUTER_ENTRY_FILE),
        "navigation_file_sha256": sha256(NAV_FILE),
        "migration_file_sha256": {Path(m["file"]).name: sha256(ROOT/m["file"]) for m in migrations},
        "metrics": {
            "local_migrations": len(migrations),
            "application_tables": len(schema),
            "application_columns_after_000017": sum(len(s["columns"]) for s in schema.values()),
            "declared_foreign_keys": sum(len(s["foreign_keys"]) for s in schema.values()),
            "api_routes_including_options": len(routes),
            "openapi_operations": len(openapi),
            "openapi_paths": len(spec["paths"]),
            "route_not_in_openapi": len(missing),
            "openapi_not_in_route": len(extra),
            "frontend_named_routes": len(frontend),
            "phpunit_test_files": len(tests["phpunit"]),
            "vitest_spec_files": len(tests["vitest"]),
            "playwright_spec_files": len(tests["playwright"]),
        },
        "routes": routes,
        "openapi_operations": openapi,
        "backend_openapi_missing": missing,
        "openapi_backend_missing": extra,
        "frontend_routes": frontend,
        "schema": schema,
        "migrations": migrations,
        "test_files": tests,
    }
    (OUT/"inventory.json").write_text(json.dumps(manifest,indent=2,ensure_ascii=False)+"\n",encoding="utf-8")
    print(json.dumps(manifest["metrics"],ensure_ascii=False,indent=2))
    for mismatch in missing + extra:
        note = "PREFLIGHT (expected):" if mismatch["method"] == "OPTIONS" else "MISMATCH:"
        print(note,mismatch["method"],mismatch["path"])


if __name__ == "__main__":
    main()
