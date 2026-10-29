#!/usr/bin/env python3
"""Offline M10.2 guard; blocks new UI callsites without explicit PBAC mode."""
from pathlib import Path
import re

root = Path(__file__).resolve().parents[2]
frontend = root / 'frontend' / 'src'
backend = root / 'backend' / 'app'
vue_files = list(frontend.rglob('*.vue'))
matching = [p for p in vue_files if '<TableRowActions' in p.read_text(encoding='utf-8')]
counts = 0
for file in matching:
    text = file.read_text(encoding='utf-8')
    for match in re.finditer(r'<TableRowActions\b[\s\S]*?/>', text):
        chunk = match.group(0)
        assert 'authorization-mode="effective"' in chunk, f'Non-strict actions in {file}'
        assert ':view-scope-verified="true"' in chunk, f'No VIEW proof in {file}'
        assert ':allowed-actions=' in chunk or file.name == 'ProductImportJobsView.vue', f'Missing hints in {file}'
        counts += 1
assert counts == 21 and len(matching) == 20, f'Expected 21 callsites/20 components, got {counts}/{len(matching)}'
component = (frontend / 'components/lists/TableRowActions.vue').read_text(encoding='utf-8')
assert "authorizationMode: 'effective'" in component
assert 'snapshotState.value === \'ready\'' in component
assert "canMutation('edit')" in component and "canMutation('delete')" in component
for file in (
    'Modules/Product/Catalog/ProductCatalogService.php',
    'Modules/People/Read/PeopleMasterReadService.php',
    'Modules/Organization/Read/OrganizationPeopleReadService.php',
    'Modules/Contact/ContactService.php',
    'Modules/Pricing/PriceList/PriceListService.php',
    'Modules/Access/Management/AccessReadService.php',
    'Modules/Workflow/Management/WorkflowReadService.php',
    'Modules/Identity/Management/IdentityReadService.php',
    'Modules/Location/Read/LocationMasterReadService.php',
):
    assert 'EffectiveRowActionHints::forViewedRow' in (backend / file).read_text(encoding='utf-8'), file
contract = root / 'contracts/openapi/openapi.yaml'
frozen = root / 'docs/backend-rewrite/openapi-frozen-v1.2.4.yaml'
assert contract.exists() and frozen.exists()
print(f'M10.2 SOURCE COVERAGE: {counts} effective UI callsites, 9 backend read paths, frozen contracts intact (existence) PASS')
