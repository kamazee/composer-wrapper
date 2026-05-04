<?php

require __DIR__ . '/BaseTestCase.php';

// output buffer manipulations to get rid of shebang (#!/usr/bin/env php)
$bootstrapFailureMessages = array();

set_error_handler(function ($severity, $message, $file, $line) use (&$bootstrapFailureMessages) {
    $bootstrapFailureMessages[] = sprintf('PHP Error: %s in %s:%d', $message, $file, $line);

    return true;
});

set_exception_handler(function ($exception) use (&$bootstrapFailureMessages) {
    $bootstrapFailureMessages[] = sprintf(
        'Uncaught %s: %s in %s:%d',
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );
});

ob_start();
require __DIR__ . '/../composer';
ob_end_clean();

restore_exception_handler();
restore_error_handler();

if (!empty($bootstrapFailureMessages)) {
    fwrite(STDERR, implode(PHP_EOL, $bootstrapFailureMessages) . PHP_EOL);
    exit(1);
}
