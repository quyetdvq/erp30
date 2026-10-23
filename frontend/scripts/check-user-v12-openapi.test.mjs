import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

const root = fileURLToPath(new URL('../../contracts/openapi/openapi.yaml', import.meta.url));
const spec = readFileSync(root, 'utf8');

function component(section, name, yaml = spec) {
  const normalized = yaml.replace(/\r\n?/g, '\n');
  const sectionStart = normalized.indexOf(`\n  ${section}:\n`);
  assert.ok(sectionStart >= 0, `component section ${section} must exist`);
  const tail = normalized.slice(sectionStart);
  const key = `\n    ${name}:`;
  const start = tail.indexOf(key);
  assert.ok(start >= 0, `${section}.${name} must exist`);
  const following = tail.slice(start + key.length);
  const next = following.search(/\n    [A-Za-z][A-Za-z0-9_]*:/);
  return tail.slice(start, next === -1 ? undefined : start + key.length + next);
}

function schema(name) {
  return component('schemas', name);
}

function pathItem(path) {
  const key = `  ${path}:`;
  const start = spec.indexOf(key);
  assert.ok(start >= 0, `path ${path} must exist`);
  const next = spec.indexOf('\n  /api/v1/', start + key.length);
  return spec.slice(start, next === -1 ? undefined : next);
}

test('create and profile schema remain backward compatible with optional name and groups', () => {
  const create = schema('UserIdentityCreate');
  assert.match(create, /required: \[username, email, password, state\]/);
  assert.match(create, /name: \{type: string, minLength: 1, maxLength: 255/);
  assert.match(create, /group_ids: \{type: array, uniqueItems: true/);
  assert.match(create, /pattern: '\^\[1-9\]\[0-9\]\*\$'/);
  assert.doesNotMatch(create, /avatar_filename|avatar_url/);

  const patch = schema('UserIdentityPatch');
  assert.match(patch, /name: \{type: string, minLength: 1, maxLength: 255/);
  assert.doesNotMatch(patch, /group_ids|avatar_filename/);

  const profile = schema('AuthProfilePatch');
  assert.match(profile, /minProperties: 1/);
  assert.match(profile, /name: \{type: string, minLength: 1, maxLength: 255/);
  assert.doesNotMatch(profile, /required: \[username, email\]/);
});

test('public user schemas expose safe static URLs but no storage filenames', () => {
  for (const name of ['UserIdentity', 'SecurityUser', 'AuthUser']) {
    const body = schema(name);
    assert.match(body, /has_avatar:/, `${name} must expose has_avatar`);
    assert.match(body, /name:/, `${name} must expose name`);
    assert.doesNotMatch(body, /avatar_filename/, `${name} must not leak storage filenames`);
    assert.match(body, /avatar_url:/, `${name} must expose optional static avatar URL`);
  }
});

test('avatar path has documented POST multipart and DELETE; image reads are static URLs', () => {
  const avatar = pathItem('/api/v1/users/{id}/avatar');
  for (const operationId of ['uploadUserAvatar', 'deleteUserAvatar']) {
    assert.match(avatar, new RegExp(`operationId: ${operationId}\\b`));
  }
  assert.match(avatar, /\n    post:\s*\n      operationId: uploadUserAvatar/);
  assert.doesNotMatch(avatar, /\n    put:|operationId: getUserAvatar|\n    get:/);
  assert.doesNotMatch(spec, /UserAvatarBinary:/);
  const request = component('requestBodies', 'UserAvatarUpload');
  assert.match(request, /multipart\/form-data/);
  assert.match(request, /required: \[image\]/);
  assert.match(avatar, /UserAvatarMutationResponse/);
  assert.match(avatar, /UnprocessableEntity/);
  assert.match(avatar, /InternalServerError/);
  assert.match(schema('UserIdentity'), /avatar_url:/);
});

test('existing account and profile operation IDs are unchanged', () => {
  assert.match(pathItem('/api/v1/users'), /operationId: createUser\b/);
  assert.match(pathItem('/api/v1/users/{id}'), /operationId: updateUser\b/);
  assert.match(pathItem('/api/v1/auth/profile'), /operationId: updateAuthProfile\b/);
});

// Regression: Windows Git checkouts can use CRLF, which must not break component lookup.
test('component sections remain discoverable in CRLF OpenAPI checkouts', () => {
  const windowsYaml = spec.replace(/\r?\n/g, '\r\n');
  assert.match(component('schemas', 'UserIdentityCreate', windowsYaml), /group_ids:/);
  assert.match(component('schemas', 'UserIdentity', windowsYaml), /avatar_url:/);
  assert.match(component('requestBodies', 'UserAvatarUpload', windowsYaml), /multipart\/form-data/);
});

// Inline flow mappings must quote descriptions containing commas or Redocly rejects them.
test('avatar image upload description is one quoted YAML scalar', () => {
  const body = component('requestBodies', 'UserAvatarUpload');
  assert.match(body, /image: \{type: string, format: binary, description: '[^']*,[^']*'\}/);
});
