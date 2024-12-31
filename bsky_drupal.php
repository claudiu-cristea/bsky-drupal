#!/usr/bin/env php
<?php

declare(strict_types=1);

use BSkyDrupal\App;
use BSkyDrupal\Logger\CompositeLogger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;

require_once __DIR__ . '/vendor/autoload.php';

$fileName = rtrim((string)getenv('BSKY_LOG_PATH'), DIRECTORY_SEPARATOR) . '/bsky_drupal.log';
$fileHandler = (new RotatingFileHandler($fileName, 3))->setFilenameFormat(
    '{filename}-{date}',
    RotatingFileHandler::FILE_PER_MONTH,
);

$logger = new CompositeLogger(
    'bsky_drupal',
    $fileHandler,
    new StreamHandler('php://stdout'),
    new StreamHandler('php://stderr'),
);

exit((new App($logger))->run());
