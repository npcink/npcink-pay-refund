<?php
/** Bind an unpublished candidate ZIP to its exact source and packaged file contents. */
if (PHP_SAPI !== 'cli' || count($argv) !== 5) {
    throw new RuntimeException('Usage: php bin/build-manifest.php ZIP ROOT VERSION STAGE');
}
list(, $zip, $root, $version, $stage) = $argv;
$git = static function ($arguments) use ($root) {
    $process = proc_open(array_merge(array('git', '-C', $root), $arguments),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('file', '/dev/null', 'w')), $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start Git for build identity.'); }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    if (0 !== proc_close($process)) { throw new RuntimeException('Cannot read Git build identity.'); }
    return $output;
};
// Use NUL-delimited filenames, including untracked nonignored files. A tracked diff
// alone omits new implementation/tests and cannot identify this working tree.
$source_files = array();
foreach (explode("\0", $git(array('ls-files', '--cached', '--others', '--exclude-standard', '-z'))) as $path) {
    if ('' === $path) { continue; }
    $file = $root . '/' . $path;
    $source_files[$path] = is_file($file) ? hash_file('sha256', $file) : null;
}
ksort($source_files, SORT_STRING);
$package_files = array();
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (!$file->isFile()) { continue; }
    $package_files[substr($file->getPathname(), strlen(rtrim($stage, '/')) + 1)] = hash_file('sha256', $file->getPathname());
}
ksort($package_files, SORT_STRING);
$sha = hash_file('sha256', $zip);
$manifest = array(
    'version' => $version, 'zip' => basename($zip), 'sha256' => $sha,
    'built_at_utc' => gmdate('c'), 'php' => PHP_VERSION,
    'composer_lock_sha256' => hash_file('sha256', $root . '/composer.lock'),
    'git_commit' => trim($git(array('rev-parse', 'HEAD'))),
    'tracked_diff_sha256' => hash('sha256', $git(array('diff', 'HEAD', '--binary'))),
    'git_status' => trim($git(array('status', '--porcelain'))),
    'source_tree_sha256' => hash('sha256', json_encode($source_files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
    'source_files_sha256' => $source_files,
    'packaged_files_sha256' => $package_files,
    'merchant_validation' => 'pending',
);
if (false === file_put_contents($zip . '.sha256', $sha . '  ' . basename($zip) . "\n")
    || false === file_put_contents($zip . '.manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n")) {
    throw new RuntimeException('Cannot write build identity files.');
}
