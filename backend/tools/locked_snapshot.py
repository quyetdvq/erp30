#!/usr/bin/env python3
"""Shared read-only, pinned per-file SHA-256 baseline verification.

Both manifests were generated from the unchanged erp_v1.2.4.zip archive.
Never derive expected hashes from the directory currently under test.
"""
from __future__ import annotations

import hashlib
import json
from pathlib import Path

PINNED_MANIFESTS = {
    'frontend-fingerprint-v1.2.4.json': (
        'cc0895080ade5eacf7fa0861f9ab3351a572784defe7358286cbb213819beb28', 277),
    'backend-baseline-fingerprint-v1.2.4.json': (
        '9d50b3f6bef7b216dc0410990ba6551545b55881114679aca3f1645a8c190a0e', 806),
}
SOURCE_ZIP_SHA256 = '64d612196ef88e773b8f8015ed64881d3550ad02cb250405a3c5bccf7415507b'


def sha256(contents: bytes) -> str:
    return hashlib.sha256(contents).hexdigest()


def load_manifest(file: Path) -> dict[str, str]:
    """Verify the pinned JSON with only Windows CRLF/LF transport equivalence.

    The manifest's canonical LF bytes remain SHA-256 pinned to the original
    v1.2.4 release. This exception applies ONLY to the manifest text itself;
    verify_directory() still hashes protected source files byte-for-byte.
    """
    pinned, count = PINNED_MANIFESTS[file.name]
    if not file.is_file() or file.is_symlink():
        raise AssertionError(f'Pinned snapshot missing or symlink: {file}')
    raw = file.read_bytes()
    canonical = raw.replace(b'\r\n', b'\n')
    actual_sha = sha256(canonical)
    if actual_sha != pinned:
        raise AssertionError(
            f'Pinned snapshot modified: {file.name}; canonical SHA-256 '
            f'{actual_sha} != expected {pinned}. Only CRLF/LF conversion is allowed.'
        )
    document = json.loads(canonical.decode('utf-8'))
    if raw != canonical:
        print(f'PASS: {file.name} CRLF-only line endings normalized; pinned SHA-256 verified')
    if document.get('reference_sha256') != SOURCE_ZIP_SHA256:
        raise AssertionError(f'Snapshot source does not match v1.2.4: {file.name}')
    entries = document.get('files')
    if not isinstance(entries, list) or len(entries) != count:
        raise AssertionError(f'Snapshot file count differs from signed v1.2.4: {file.name}')
    mapping: dict[str, str] = {}
    for entry in entries:
        path, digest = entry.get('path'), entry.get('sha256')
        if (not isinstance(path, str) or not path or path.startswith('/') or
                '\\' in path or '..' in Path(path).parts or
                not isinstance(digest, str) or len(digest) != 64 or path in mapping):
            raise AssertionError(f'Invalid pinned snapshot entry: {file.name}')
        mapping[path] = digest
    return mapping


def verify_directory(directory: Path, expected: dict[str, str], label: str,
                     *, reference_label: str = 'v1.2.4',
                     ignored_root_dirs: tuple[str, ...] = (),
                     ignored_root_files: tuple[str, ...] = (),
                     ignored_root_prefixes: tuple[str, ...] = (),
                     ignored_root_suffixes: tuple[str, ...] = (),
                     ignored_local_editor_files: tuple[str, ...] = ()) -> None:
    """Match all source bytes; optionally prune known generated/editor metadata.

    Root-level build output may be omitted. For the frontend only, the two
    explicit .vscode editor metadata files may be omitted, never their entire
    directory. Source files and symlinks remain subject to fail-closed checks.
    """
    if not directory.is_dir() or directory.is_symlink():
        raise AssertionError(f'{label}: directory missing or symbolic link')
    safe_editor_files = {'.vscode/extensions.json', '.vscode/settings.json'}
    if (any(name not in safe_editor_files or name in expected
            for name in ignored_local_editor_files)):
        raise AssertionError(f'{label}: unsafe local editor-file exclusion')
    actual: dict[str, str] = {}
    pending = [directory]
    while pending:
        current = pending.pop()
        for item in current.iterdir():
            relative = item.relative_to(directory)
            is_root_child = len(relative.parts) == 1
            if is_root_child and (
                    item.name in ignored_root_dirs or
                    (not item.is_dir() and (
                        item.name in ignored_root_files or
                        item.name.startswith(ignored_root_prefixes) or
                        item.name.endswith(ignored_root_suffixes)
                    ))):
                continue
            if item.is_symlink():
                raise AssertionError(f'{label}: symbolic link forbidden: {relative}')
            if relative.as_posix() in ignored_local_editor_files:
                if not item.is_file():
                    raise AssertionError(f'{label}: editor metadata is not a file: {relative}')
                continue
            if item.is_dir():
                pending.append(item)
            elif item.is_file():
                actual[relative.as_posix()] = sha256(item.read_bytes())
            else:
                raise AssertionError(f'{label}: unsupported file type: {relative}')
    missing = sorted(expected.keys() - actual.keys())
    added = sorted(actual.keys() - expected.keys())
    changed = sorted(path for path in expected.keys() & actual.keys()
                     if expected[path] != actual[path])
    if missing or added or changed:
        details = '; '.join(f'{name}={len(paths)} example={paths[:4]}' for name, paths
                            in [('missing', missing), ('added', added), ('changed', changed)])
        raise AssertionError(f'{label}: SHA-256 mismatch; {details}')
    print(f'PASS: {label} matches {reference_label} ({len(actual)}/{len(expected)} files)')
