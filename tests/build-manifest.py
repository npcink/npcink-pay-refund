#!/usr/bin/env python3
"""Check provenance with a disposable Git tree and independently inspect a ZIP."""
import hashlib
import json
import pathlib
import shutil
import subprocess
import sys
import tempfile
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[1]


def sha(data):
    return hashlib.sha256(data).hexdigest()


def check_zip(archive):
    archive = pathlib.Path(archive)
    manifest = json.loads(pathlib.Path(str(archive) + '.manifest.json').read_text())
    assert manifest['sha256'] == sha(archive.read_bytes()), 'ZIP hash mismatch'
    assert pathlib.Path(str(archive) + '.sha256').read_text().split()[0] == manifest['sha256'], 'Sidecar hash mismatch'
    with zipfile.ZipFile(archive) as package:
        names = [item.filename for item in package.infolist() if not item.is_dir()]
        assert len(names) == len(set(names)), 'Duplicate ZIP file entries'
        entries = {name.split('/', 1)[1]: sha(package.read(name)) for name in names}
    assert entries == manifest['packaged_files_sha256'], 'Manifest must cover every ZIP file exactly'
    assert manifest['merchant_validation'] == 'pending', 'Build cannot assert merchant validation'


with tempfile.TemporaryDirectory(prefix='npcink-manifest-') as directory:
    fixture = pathlib.Path(directory)
    source = fixture / 'source'; source.mkdir()
    stage = fixture / 'stage'; stage.mkdir()
    (source / 'composer.lock').write_text('{}')
    (source / '.gitignore').write_text('ignored\n')
    subprocess.run(['git', 'init', '-q', str(source)], check=True)
    subprocess.run(['git', '-C', str(source), 'add', '.'], check=True)
    subprocess.run(['git', '-C', str(source), '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-qm', 'fixture'], check=True)
    (source / 'new file.php').write_text('first')
    (source / 'ignored').write_text('ignored')
    (stage / 'plugin.php').write_text('synthetic package')
    archive = fixture / 'candidate.zip'
    with zipfile.ZipFile(archive, 'w') as package:
        package.write(stage / 'plugin.php', 'plugin/plugin.php')

    def generate():
        subprocess.run([shutil.which('php'), str(ROOT / 'bin/build-manifest.php'), str(archive), str(source), 'test', str(stage)], check=True)
        return json.loads(pathlib.Path(str(archive) + '.manifest.json').read_text())

    first = generate()
    assert first['source_files_sha256']['new file.php'] == sha(b'first'), 'Untracked file content missing'
    assert 'ignored' not in first['source_files_sha256'], 'Ignored files must not enter source identity'
    (source / 'new file.php').write_text('second')
    second = generate()
    assert first['tracked_diff_sha256'] == second['tracked_diff_sha256'], 'Fixture tracked diff changed'
    assert first['source_tree_sha256'] != second['source_tree_sha256'], 'Untracked content changes must change source identity'
    check_zip(archive)
    print('Build identity fixture passed: untracked changes, ignored files, ZIP entries and hashes.')

if len(sys.argv) > 1:
    check_zip(sys.argv[1])
    print('Candidate ZIP and complete file manifest verified.')
