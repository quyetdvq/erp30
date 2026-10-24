#!/usr/bin/env python3
"""CI4 route gate: frozen M1 + reviewed PBAC read-only M2.1 surface."""
import json
from pathlib import Path
import re

here = Path(__file__).resolve().parent
root = here.parents[1]
manifest = json.loads((root/'docs/backend-rewrite/openapi-operations-v1.2.4.json').read_text())
known = {(x['method'], x['path']) for x in manifest['operations']}
route_text = (root/'backend/app/Config/Routes.php').read_text()
routes = re.findall(r"\$routes->(get|post|put|patch|delete)\('([^']+)'", route_text)
actual = {(method.upper(), '/api/v1/' + path) for method,path in routes}
expected = {
    ('GET','/api/v1/health'),
    ('GET','/api/v1/auth/csrf'),
    ('GET','/api/v1/auth/me'),
    ('POST','/api/v1/auth/login'),
    ('POST','/api/v1/auth/logout'),
    ('PATCH','/api/v1/auth/profile'),
}
read_resources = ['provinces', 'wards', 'organizations', 'departments', 'positions', 'employees', 'employee-assignments']
for name in read_resources:
    expected.add(('GET', '/api/v1/'+name))
    expected.add(('GET', '/api/v1/'+name+'/{id}'))
expected.add(('GET', '/api/v1/organizations/tree'))
expected.add(('POST', '/api/v1/organizations/labels/resolve'))
# Normalize CI4 numeric placeholders to the OpenAPI id parameter.
normalized = {(method, path.replace('(:num)', '{id}')) for method, path in actual}
assert normalized == expected, f'Registered route drift missing={expected-normalized} extra={normalized-expected}'
assert normalized.issubset(known), f'Noncontract routes: {normalized-known}'
assert 'service(\'auth\')->routes' not in route_text, 'Shield web routes exposed'
modules = (root/'backend/app/Config/Modules.php').read_text()
assert not any(line.strip() == "'routes'," for line in modules.splitlines()), 'Vendor module route discovery must be disabled'
filters = (root/'backend/app/Config/Filters.php').read_text()
assert "'csrf'" in filters and "'requestmeta'" in filters, 'Core protection filters missing'
routing = (root/'backend/app/Config/Routing.php').read_text()
assert 'public bool $autoRoute = false;' in routing, 'AutoRoute must be disabled'
assert 'public bool $useControllerAttributes = false;' in routing, 'Attribute routing must be disabled'
sec = (root/'backend/app/Config/Security.php').read_text()
for expected_text in ("'session'", "'X-CSRF-TOKEN'", 'public bool $regenerate = true;', 'public bool $redirect = false;'):
    assert expected_text in sec, f'Missing CSRF policy: {expected_text}'
print(f'PASS: {len(normalized)} M1+M2.1 API operations, all in frozen {len(known)}-operation contract')
print('PASS: no non-contract mutations, Shield web routes or auto-routing')
print('PASS: session CSRF rotation and JSON error policy configured')

read_controller = (root/'backend/app/Modules/ReadApi/ReadCatalogController.php').read_text()
read_gate = (root/'backend/app/Modules/Access/ReadViewGate.php').read_text()
# M3.2 wraps the accepted M2.1.1 gate; default mode remains legacy.
bridge = (root/'backend/app/Modules/Access/ReadAccessBridge.php').read_text()
assert 'new ReadAccessBridge($user)' in read_controller or 'new ReadAccessBridge($user,' in read_controller
assert "['legacy', 'shadow', 'enforce']" in bridge
assert "env('DINKERP_M32_READ_MODE', 'legacy')" in bridge
assert "return $this->legacy()->canView($type, $persistedRow)" in bridge or "return $old;" in bridge
assert "if ($this->mode === 'enforce')" in bridge
assert 'new ReadViewGate(' in bridge and 'new UnifiedAccessService(' in bridge
assert 'if ($gate->canView($resource, $row))' in read_controller
assert 'AuthorizedListPage::paginate' in read_controller
assert "FlatHierarchy::visibleRows($page['data'])" in read_controller
for needle in ('BasePermissionReader', 'ScopeFactsReader', 'PolicyReadLanes::allows', "BINARY p.status = 'ACTIVE'"):
    assert needle in read_gate, f'Missing PBAC read protection: {needle}'
print('PASS: every list/page/tree path traverses the read PBAC bridge (legacy by default)')

# Regression: CI4 4.7.5 interprets "Controller::index/resource" as a controller
# path. Use array-style handlers that resolve to an actual public method.
assert "ReadCatalogController::class . '::index/" not in route_text
assert "ReadCatalogController::class . '::show/" not in route_text
controller_text = (root/'backend/app/Modules/ReadApi/ReadCatalogController.php').read_text()
expected_handlers = {}
for base, plural, singular in [
    ('provinces', 'Provinces', 'Province'),
    ('wards', 'Wards', 'Ward'),
    ('organizations', 'Organizations', 'Organization'),
    ('departments', 'Departments', 'Department'),
    ('positions', 'Positions', 'Position'),
    ('employees', 'Employees', 'Employee'),
    ('employee-assignments', 'EmployeeAssignments', 'EmployeeAssignment'),
]:
    expected_handlers[base] = 'list' + plural
    expected_handlers[base + '/(:num)'] = 'show' + singular
for path, method in expected_handlers.items():
    registration = "$routes->get('" + path + "', [ReadCatalogController::class, '" + method + "']);"
    assert registration in route_text, f'Invalid handler for {path}: must use array controller and {method}'
    assert re.search(r'public function ' + method + r'\(', controller_text), f'Absent public handler: {method}'
print('PASS: all 14 resource GET route handlers resolve via explicit controller methods')
