<?php

require __DIR__ . '/BaseTestCase.php';

// output buffer manipulations to get rid of shebang (#!/usr/bin/env php)
ob_start();
require __DIR__ . '/../composer';
ob_end_clean();
