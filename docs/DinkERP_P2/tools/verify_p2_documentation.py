#!/usr/bin/env python3
"""Independent, read-only P2 documentation verification. Python 3.10+, stdlib only.
No DB, no production network, and no code mutations.
"""
from pathlib import Path
from collections import Counter
import re,csv,json,hashlib,sys
ROOT=Path(__file__).resolve().parent.parent
errors=[];checks=[]

def yes(cond,message):
    (checks if cond else errors).append(message)
    print(('PASS' if cond else 'FAIL')+' '+message)

def rows(p):
    with (ROOT/p).open(encoding='utf-8-sig',newline='') as f:return list(csv.DictReader(f))

def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()

src=ROOT/'reference/00_User_Requirements_v1.0.15.md'
meta=json.loads((ROOT/'reference/BASELINE_METADATA.json').read_text(encoding='utf-8'))
s=src.read_text(encoding='utf-8')
yes(sha(src)==meta['source_requirements_sha256'],'Original UR source SHA-256 matches baseline metadata')
head=list(re.finditer(r'^(#{2,3})\s+(.+)$',s,re.M))
req=[]
for i,h in enumerate(head):
    mat=re.match(r'(UR-[A-Z]+-\d+)\s*-\s*(.*)',h.group(2))
    if not mat:continue
    body=s[h.end():head[i+1].start() if i+1<len(head) else len(s)]
    p=re.search(r'^\*\*Target phase:\*\*\s*(P\d)',body,re.M)
    if p and p.group(1)=='P2':
        tail=body.split('**Acceptance criteria**',1)[1]
        ac=[x for x in tail.splitlines() if re.match(r'^\s*-\s+',x)]
        original=s[h.start():head[i+1].start() if i+1<len(head) else len(s)].strip()
        req.append((mat.group(1),len(ac),original))
allids={r[0] for r in req}
yes(len(req)==30 and len(allids)==30,'Exactly 30 P2 user requirements, unique IDs')
yes(sum(r[1] for r in req)==147,'All 147 P2 acceptance bullets preserved')
phases=Counter(re.search(r'^\*\*Target phase:\*\*\s*(P\d)',s[h.end():head[i+1].start() if i+1<len(head) else len(s)],re.M).group(1) for i,h in enumerate(head) if re.match(r'UR-[A-Z]+-\d+\s*-',h.group(2)) and re.search(r'^\*\*Target phase:\*\*\s*(P\d)',s[h.end():head[i+1].start() if i+1<len(head) else len(s)],re.M))
yes(dict(phases)=={'P1':40,'P2':30,'P3':13},'83 source UR phase count P1=40 P2=30 P3=13')

ur_doc=(ROOT/'docs/01_P2_USER_REQUIREMENTS_BASELINE.md').read_text(encoding='utf-8')
yes(all(orig in ur_doc for _,_,orig in req),'Each full P2 UR original block appears verbatim in P2 UR doc')
modmap={'DOC':'DOC','CUS':'CUS','SUP':'SUP','PRC':'PRC','PUR':'PUR','INV':'INV','SAL':'SAL','FIN':'FIN'}
for module in modmap:
    path=ROOT/f'modules/P2_{module}_INDEPENDENT_MODULE_SPEC.md'
    text=path.read_text(encoding='utf-8') if path.exists() else ''
    yes(all(original in text for uid,_,original in req if uid.startswith('UR-'+module+'-')),
        f'{module} standalone module contains all source UR blocks')

cross=json.loads((ROOT/'reference/P2_EXACT_CROSS_SECTION_PROVENANCE.json').read_text(encoding='utf-8'))
yes(len(cross)==3 and all(r['body'] in s and r['body'] in ur_doc and hashlib.sha256(r['body'].encode()).hexdigest()==r['sha256'] for r in cross),
    'Exact original phase, extended pricing and P2 operational flows preserved')

accept=rows('reference/P2_REQUIREMENTS_AND_ACCEPTANCE.csv')
trace=rows('reference/P2_END_TO_END_TRACEABILITY.csv')
tests=rows('reference/P2_TEST_CASE_REGISTRY.csv')
api=rows('reference/P2_API_OPERATION_CATALOG.csv')
ui=rows('reference/P2_UI_SCREEN_CATALOG.csv')
db=rows('reference/P2_DATABASE_ENTITY_CATALOG.csv')
decisions=rows('reference/P2_OPEN_DECISIONS.csv')
yes(len(accept)==147 and len(set(x['fr_id'] for x in accept))==147 and len(set(x['test_id'] for x in accept))==147,
    '147 unique FR IDs and 147 corresponding original AC test IDs')
yes(set(x['ur_id'] for x in accept)==allids,'All 30 UR mapped to Acceptance criteria CSV')
yes(len(trace)==147 and set(x['test_id'] for x in trace)==set(x['test_id'] for x in accept),'147 UR→FR→DB→API→UI→Test trace rows present')
yes(len(tests)==189 and len([x for x in tests if x['type']=='BASELINE-AC'])==147 and len([x for x in tests if x['type']=='CROSS-CUTTING-NEGATIVE'])==42,'189 test registry rows = 147 base + 42 negative')
yes(all(x['execution_status']=='NOT_RUN' for x in tests),'No unexecuted P2 tests falsely marked PASS')
yes(len(api)==187 and len(set((x['method'],x['path']) for x in api))==187 and len(set(x['operation_id'] for x in api))==187,'187 unique candidate P2 API methods and operationIds')
yes(len(set(x['path'] for x in api))==130,'130 proposed API path templates')
yes(len(ui)==31 and len(set(x['screen_id'] for x in ui))==31,'31 unique candidate UI screens')
yes(len(db)==62 and len(set(x['table'] for x in db))==62,'62 unique proposed database entities')
yes(len(decisions)==35 and all(x['status']=='OPEN' for x in decisions),'35 explicitly OPEN design decisions')
yes(all(x['primary_table_draft'] in {r['table'] for r in db} and x['api_path_draft'] in {r['path'] for r in api} and x['ui_id_draft'] in {r['screen_id'] for r in ui} for x in trace),'All trace DB/API/UI targets exist in the respective catalogs')
yes(all((ROOT/x['module_spec']).is_file() for x in trace),'All trace module documentation targets exist')
yes(all(x['design_status']=='P2-DRAFT' and x['qa_status']=='NOT_RUN' for x in trace),'Traceability does not claim implemented modules or QA success')

yml=(ROOT/'contracts/openapi/p2.draft.openapi.yaml').read_text(encoding='utf-8')
ops=set(re.findall(r'^\s+operationId:\s*([^\s]+)\s*$',yml,re.M))
paths=set(re.findall(r'^  (/api/v1/[^:]+):\s*$',yml,re.M))
yes(len(ops)==187 and ops=={r['operation_id'] for r in api},'OpenAPI YAML declares exactly 187 catalog operation IDs')
yes(len(paths)==130 and paths=={r['path'] for r in api},'OpenAPI YAML paths match 130 candidate path templates')
yes('x-document-status: P2-DRAFT-NOT-IMPLEMENTED' in yml and 'example.invalid' in yml,'Draft OpenAPI clearly marked unimplemented/placeholder')
# Basic local link validation (README and all module Markdown) without a Markdown dependency.
missing=[]
for m in ROOT.rglob('*.md'):
    text=m.read_text(encoding='utf-8')
    for link in re.findall(r'(?<!\!)\[[^\]]+\]\(([^)]+)\)',text):
        if link.startswith(('http://','https://','#','mailto:')):continue
        target=link.split('#')[0]
        if target and not (m.parent/target).exists():missing.append((str(m.relative_to(ROOT)),link))
yes(not missing,'Internal Markdown links exist'+(' '+str(missing[:5]) if missing else ''))
manifest_file=ROOT/'reference/P2_PACKAGE_MANIFEST.json'
if manifest_file.exists():
    manifest=json.loads(manifest_file.read_text(encoding='utf-8'))
    actual={str(x.relative_to(ROOT)).replace('\\','/'):sha(x) for x in ROOT.rglob('*') if x.is_file() and x!=manifest_file}
    yes(manifest['files']==actual, f"Package manifest exact bytes match ({len(actual)} files)")
else:print('INFO Package manifest is not generated yet; final distribution must include and verify it')
print('CHECKS:',len(checks),'PASS,',len(errors),'FAIL')
if errors:
    print('ERRORS:',*errors,sep='\n  - ')
    sys.exit(1)
