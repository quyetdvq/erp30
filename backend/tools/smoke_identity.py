#!/usr/bin/env python3
"""Read-oriented HTTP smoke for a running M1 backend against TEST database only."""
import argparse
import http.cookiejar
import json
import urllib.error
import urllib.request
import uuid

parser = argparse.ArgumentParser()
parser.add_argument('--base', default='http://127.0.0.1:8081')
parser.add_argument('--origin', default='http://localhost:5173')
parser.add_argument('--username', help='Only for a dedicated test account')
parser.add_argument('--password')
args = parser.parse_args()

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
base = args.base.rstrip('/')


def call(method, path, body=None, csrf=None):
    raw = json.dumps(body).encode() if body is not None else None
    h = {'Accept': 'application/json', 'Accept-Language': 'vi',
         'X-Request-ID': str(uuid.uuid4()), 'Origin': args.origin}
    if raw is not None:
        h['Content-Type'] = 'application/json'
    if csrf:
        h['X-CSRF-TOKEN'] = csrf
    req = urllib.request.Request(base + path, data=raw, headers=h, method=method)
    try:
        resp = opener.open(req, timeout=12)
    except urllib.error.HTTPError as error:
        resp = error
    payload = resp.read()
    parsed = json.loads(payload) if payload else None
    req_id = resp.headers.get('X-Request-ID')
    assert req_id and parsed is not None and parsed['meta']['request_id'] == req_id, (path, 'request ID mismatch')
    assert resp.headers.get('Content-Language') == 'vi', (path, 'locale mismatch')
    acao = resp.headers.get('Access-Control-Allow-Origin')
    assert acao == args.origin, (path, 'CORS origin mismatch', acao)
    return resp.status, parsed, resp.headers


status, body, h = call('GET', '/api/v1/health')
assert status == 200 and body['data']['status'] == 'ok'
print('PASS health')
status, body, h = call('GET', '/api/v1/auth/me')
assert status == 401 and body['error']['code'] == 'UNAUTHENTICATED'
print('PASS unauthenticated me')
status, body, h = call('GET', '/api/v1/auth/csrf')
assert status == 200 and body['data']['header_name'] == 'X-CSRF-TOKEN'
csrf = body['data']['csrf_token']
assert isinstance(csrf, str) and csrf
print('PASS csrf bootstrap')
status, body, h = call('POST', '/api/v1/auth/login', {'email': '', 'password': ''}, csrf)
assert status == 422 and body['error']['code'] == 'INVALID_CREDENTIALS'
csrf = h.get('X-CSRF-TOKEN')
assert csrf
print('PASS validation and CSRF rotation')
if args.username and args.password:
    status, body, h = call('POST', '/api/v1/auth/login', {
        'email': args.username, 'password': args.password, 'remember': False,
    }, csrf)
    assert status == 200 and body['data']['user']['username'], body
    csrf = h.get('X-CSRF-TOKEN')
    assert csrf
    print('PASS login')
    status, body, h = call('GET', '/api/v1/auth/me')
    assert status == 200 and isinstance(body['data']['user']['base_permissions'], list)
    print('PASS authenticated me/base permissions')
    status, body, h = call('POST', '/api/v1/auth/logout', {}, csrf)
    assert status == 200 and body['data']['ok'] is True
    print('PASS logout')
else:
    print('SKIP test-account login/logout (provide --username and --password)')
print('M1 HTTP smoke checks passed')
