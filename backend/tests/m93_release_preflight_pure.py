#!/usr/bin/env python3
"""Small filesystem fixture tests; no database, credentials or HTTP calls."""
from __future__ import annotations

import importlib.util
import hashlib
from pathlib import Path
import shutil
import tempfile
import unittest
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[2]
import sys
sys.path.insert(0, str(ROOT / 'backend/tools'))
MODULE = ROOT / 'backend/tools/m93_release_preflight.py'
spec = importlib.util.spec_from_file_location('m93_release_preflight', MODULE)
assert spec and spec.loader
m93 = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m93)
from locked_snapshot import verify_directory  # noqa: E402


class M93ReleasePreflightTests(unittest.TestCase):
    def setUp(self) -> None:
        self.tmp = tempfile.TemporaryDirectory(prefix='m93-source-')
        self.addCleanup(self.tmp.cleanup)
        self.public = Path(self.tmp.name) / 'public'
        self.public.mkdir()
        templates = self.public / 'import-templates'
        templates.mkdir()
        official = ROOT / 'frontend/public/import-templates'
        for suffix in ('.csv', '.xlsx'):
            shutil.copyfile(official / ('product-v1' + suffix),
                            templates / ('product-v1' + suffix))

    def test_generated_workspace_root_is_excluded_but_source_is_pinned(self) -> None:
        workspace = Path(self.tmp.name) / 'workspace'
        (workspace / 'src').mkdir(parents=True)
        (workspace / 'node_modules').mkdir()
        (workspace / 'dist').mkdir()
        (workspace / 'src/main.ts').write_bytes(b'official')
        (workspace / 'node_modules/installed.js').write_bytes(b'generated')
        (workspace / 'dist/index.html').write_bytes(b'build')
        (workspace / 'tsconfig.app.tsbuildinfo').write_bytes(b'generated')
        expected = {'src/main.ts': hashlib.sha256(b'official').hexdigest()}
        kwargs = dict(ignored_root_dirs=('node_modules', 'dist'),
                      ignored_root_suffixes=('.tsbuildinfo',))
        verify_directory(workspace, expected, 'fixture', **kwargs)
        (workspace / 'src/main.ts').write_bytes(b'modified')
        with self.assertRaises(AssertionError):
            verify_directory(workspace, expected, 'fixture', **kwargs)

    def test_generated_output_nested_under_src_is_not_ignored(self) -> None:
        workspace = Path(self.tmp.name) / 'workspace2'
        (workspace / 'src/node_modules').mkdir(parents=True)
        (workspace / 'src/node_modules/not-source.js').write_bytes(b'bad')
        with self.assertRaises(AssertionError):
            verify_directory(workspace, {}, 'fixture',
                             ignored_root_dirs=('node_modules',))

    def test_local_editor_metadata_exclusion_is_exact(self) -> None:
        workspace = Path(self.tmp.name) / 'workspace-editor'
        (workspace / 'src').mkdir(parents=True)
        (workspace / '.vscode').mkdir()
        (workspace / 'src/main.ts').write_bytes(b'official')
        (workspace / '.vscode/settings.json').write_text('{}')
        (workspace / '.vscode/extensions.json').write_text('{}')
        expected = {'src/main.ts': hashlib.sha256(b'official').hexdigest()}
        editor_files = ('.vscode/settings.json', '.vscode/extensions.json')
        verify_directory(workspace, expected, 'fixture',
                         ignored_local_editor_files=editor_files)
        (workspace / '.vscode/tasks.json').write_text('{}')
        with self.assertRaises(AssertionError):
            verify_directory(workspace, expected, 'fixture',
                             ignored_local_editor_files=editor_files)
        (workspace / '.vscode/tasks.json').unlink()
        (workspace / 'src/main.ts').write_bytes(b'tampered')
        with self.assertRaises(AssertionError):
            verify_directory(workspace, expected, 'fixture',
                             ignored_local_editor_files=editor_files)

    def test_editor_metadata_exclusion_cannot_hide_source(self) -> None:
        workspace = Path(self.tmp.name) / 'workspace-source'
        (workspace / '.vscode').mkdir(parents=True)
        (workspace / '.vscode/settings.json').write_text('{}')
        with self.assertRaises(AssertionError):
            verify_directory(workspace, {}, 'fixture',
                             ignored_local_editor_files=('src/main.ts',))
        with self.assertRaises(AssertionError):
            verify_directory(workspace,
                             {'.vscode/settings.json': hashlib.sha256(b'{}').hexdigest()},
                             'fixture', ignored_local_editor_files=('.vscode/settings.json',))

    def test_official_templates_are_readable(self) -> None:
        m93.scan_static_tree(self.public)
        m93.check_import_templates(self.public)

    def test_excel_lock_is_denied(self) -> None:
        (self.public / 'import-templates/~$product-v1.xlsx').write_bytes(b'lock')
        with self.assertRaises(m93.PreflightFailure):
            m93.scan_static_tree(self.public)

    def test_numbered_office_lock_is_denied(self) -> None:
        (self.public / 'import-templates/~1$product-v1.xlsx').write_bytes(b'lock')
        with self.assertRaises(m93.PreflightFailure):
            m93.scan_static_tree(self.public)

    def test_vscode_metadata_under_public_is_denied(self) -> None:
        (self.public / '.vscode').mkdir()
        (self.public / '.vscode/settings.json').write_text('{}')
        with self.assertRaises(m93.PreflightFailure):
            m93.scan_static_tree(self.public)

    def test_dotenv_is_denied(self) -> None:
        (self.public / '.env.local').write_text('secret=placeholder')
        with self.assertRaises(m93.PreflightFailure):
            m93.scan_static_tree(self.public)

    def test_source_map_is_denied(self) -> None:
        (self.public / 'app.js.map').write_text('{}')
        with self.assertRaises(m93.PreflightFailure):
            m93.scan_static_tree(self.public)

    def test_server_side_script_is_denied(self) -> None:
        (self.public / 'shell.php').write_text('<?php // dummy ?>')
        with self.assertRaises(m93.PreflightFailure):
            m93.scan_static_tree(self.public)

    def test_invalid_csv_header_is_denied(self) -> None:
        (self.public / 'import-templates/product-v1.csv').write_text('bad,columns\n')
        with self.assertRaises(m93.PreflightFailure):
            m93.check_import_templates(self.public)

    def test_corrupted_workbook_is_denied(self) -> None:
        (self.public / 'import-templates/product-v1.xlsx').write_bytes(b'fake-xlsx')
        with self.assertRaises(m93.PreflightFailure):
            m93.check_import_templates(self.public)

    def _valid_dist(self) -> Path:
        dist = Path(self.tmp.name) / 'dist'
        shutil.copytree(self.public, dist)
        (dist / 'assets').mkdir()
        (dist / 'assets/app-index-abcd1234.js').write_text('console.log("ok")')
        (dist / 'index.html').write_text('<script type="module" '
                                        'src="/assets/app-index-abcd1234.js"></script>')
        (dist / '.htaccess').write_text('RewriteRule ^ index.html [L]\n')
        return dist

    def test_clean_built_frontend_shape(self) -> None:
        m93.check_build(self._valid_dist())

    def test_dev_entry_in_dist_denied(self) -> None:
        dist = self._valid_dist()
        (dist / 'index.html').write_text('<script type="module" '
                                        'src="/src/main.ts"></script>')
        with self.assertRaises(m93.PreflightFailure):
            m93.check_build(dist)

    def test_missing_compiled_js_denied(self) -> None:
        dist = self._valid_dist()
        (dist / 'assets/app-index-abcd1234.js').unlink()
        with self.assertRaises(m93.PreflightFailure):
            m93.check_build(dist)

    def test_traversal_compiled_asset_denied(self) -> None:
        with self.assertRaises(m93.PreflightFailure):
            m93.safe_build_asset('/assets/../sneaky.js', Path(self.tmp.name))

    def test_symlink_denied_if_supported(self) -> None:
        link = self.public / 'file-link.txt'
        try:
            link.symlink_to(self.public / 'import-templates/product-v1.csv')
        except (OSError, NotImplementedError):
            self.skipTest('User cannot create symbolic links on this host')
        with self.assertRaises(m93.PreflightFailure):
            m93.scan_static_tree(self.public)


if __name__ == '__main__':
    unittest.main(verbosity=2)
