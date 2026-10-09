<?php

$failed = false;
foreach (['src', 'tests'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../'.$directory)) as $file) {
        if ($file->isFile() && 'php' === $file->getExtension()) {
            passthru(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()), $status);
            $failed = $failed || 0 !== $status;
        }
    }
}
exit($failed ? 1 : 0);
