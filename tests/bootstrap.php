<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$testTmpDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'test-tmp';

if (! is_dir($testTmpDir) && ! mkdir($testTmpDir, 0777, true) && ! is_dir($testTmpDir)) {
    throw new RuntimeException('Unable to create test temporary directory: ' . $testTmpDir);
}

putenv('TMPDIR=' . $testTmpDir);
$_ENV['TMPDIR']    = $testTmpDir;
$_SERVER['TMPDIR'] = $testTmpDir;
