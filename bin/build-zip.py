#!/usr/bin/env python3
"""
Baut ein installierbares WordPress-Plugin-ZIP aus diesem Repository.

Aufruf (im Repo-Root):
    python bin/build-zip.py            -> immo-manager-<version>.zip
    python bin/build-zip.py --out X.zip

Das ZIP enthält den Top-Level-Ordner `immo-manager/` (wie von WordPress erwartet),
nur Laufzeit-Dateien (kein .git, keine Doku-Specs, keine Build-/IDE-Dateien) und
verwendet durchgängig Forward-Slashes (kompatibel zu Linux-Hosts).
"""
import argparse
import os
import re
import sys
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PLUGIN_SLUG = 'immo-manager'

EXCLUDE_DIRS = {
    '.git', '.github', '.claude', '.worktrees', '.playwright-mcp', '.vscode', '.idea',
    'docs', 'bin', 'node_modules', 'tests', '__pycache__',
}
EXCLUDE_FILES = {
    '.gitignore', '.gitattributes', '.editorconfig', 'composer.lock', 'phpcs.xml', 'phpcs.xml.dist',
    'phpunit.xml', 'phpunit.xml.dist', '.DS_Store', 'Thumbs.db',
}
EXCLUDE_EXT = {'.zip', '.swp', '.log'}
# Screenshots/Diagnose-Bilder im Repo-Root.
EXCLUDE_ROOT_PATTERNS = [re.compile(r'^project-.*\.(png|jpe?g)$')]


def plugin_version():
    main = os.path.join(ROOT, 'immo-manager.php')
    with open(main, encoding='utf-8') as fh:
        m = re.search(r'^\s*\*\s*Version:\s*([0-9A-Za-z.\-]+)', fh.read(), re.M)
    return m.group(1) if m else '0.0.0'


def should_skip(rel_path, is_dir):
    parts = rel_path.replace('\\', '/').split('/')
    name = parts[-1]
    if is_dir:
        return name in EXCLUDE_DIRS
    if name in EXCLUDE_FILES:
        return True
    if os.path.splitext(name)[1].lower() in EXCLUDE_EXT:
        return True
    if len(parts) == 1 and any(p.match(name) for p in EXCLUDE_ROOT_PATTERNS):
        return True
    return False


def collect_files():
    files = []
    for dirpath, dirnames, filenames in os.walk(ROOT):
        rel_dir = os.path.relpath(dirpath, ROOT)
        rel_dir = '' if rel_dir == '.' else rel_dir
        dirnames[:] = sorted(d for d in dirnames if not should_skip(os.path.join(rel_dir, d), True))
        for fn in sorted(filenames):
            rel = os.path.join(rel_dir, fn) if rel_dir else fn
            if should_skip(rel, False):
                continue
            files.append(rel)
    return files


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--out', help='Ziel-ZIP (Default: immo-manager-<version>.zip im Repo-Root)')
    args = ap.parse_args()

    version = plugin_version()
    out = args.out or os.path.join(ROOT, f'{PLUGIN_SLUG}-{version}.zip')
    files = collect_files()

    if os.path.exists(out):
        os.remove(out)

    total = 0
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for rel in files:
            abs_path = os.path.join(ROOT, rel)
            arcname = f'{PLUGIN_SLUG}/' + rel.replace('\\', '/')
            zf.write(abs_path, arcname)
            total += os.path.getsize(abs_path)

    size_mb = os.path.getsize(out) / (1024 * 1024)
    print(f'Built {os.path.relpath(out, ROOT)}  ({len(files)} files, {total / (1024 * 1024):.1f} MB raw, {size_mb:.1f} MB zipped)')
    return 0


if __name__ == '__main__':
    sys.exit(main())
