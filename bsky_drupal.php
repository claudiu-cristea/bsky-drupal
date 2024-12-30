#!/usr/bin/env php
<?php

declare(strict_types=1);

use BSkyDrupal\App;
use BSkyDrupal\Logger\CompositeLogger;
use BSkyDrupal\Model\Item;
use BSkyDrupal\Plugin\DrupalDotOrgFeed;
use BSkyDrupal\Plugin\ExtensionRelease;
use BSkyDrupal\SourceInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;

require_once __DIR__ . '/vendor/autoload.php';

const SOURCES = [
    [
        DrupalDotOrgFeed::class, [
            'feed_url' => 'https://www.drupal.org/changes/drupal/rss.xml',
            'message' => '#Drupal core change',
        ],
    ],
    [
        DrupalDotOrgFeed::class, [
            'feed_url' => 'https://www.drupal.org/security/all/rss.xml',
            'message' => '#Drupal security advisory',
        ],
    ],
    [
        DrupalDotOrgFeed::class, [
            'feed_url' => 'https://www.drupal.org/section-blog/2603760/feed',
            'message' => '#Drupal blog entry',
        ],
    ],
    [
        DrupalDotOrgFeed::class, [
            'feed_url' => 'https://www.drupal.org/project/project_module/feed/full',
            'message' => 'New #Drupal module',
        ],
    ],
    [
        ExtensionRelease::class, [
            'feed_url' => 'https://www.drupal.org/taxonomy/term/7234/feed',
        ],
    ],
];

$fileName = rtrim(getenv('BSKY_LOG_PATH'), DIRECTORY_SEPARATOR) . '/bsky_drupal.log';
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

$app = new App($logger);

$count = 0;
foreach (SOURCES as [$class, $config]) {
    $plugin = new $class();
    \assert($plugin instanceof SourceInterface);
    $plugin->setConfig($config);

    foreach ($plugin->getItems() as $item) {
        \assert($item instanceof Item);
        if ($message = $plugin->getMessage($item)) {
            if ($app->postText($message, $item->url, $item->time)) {
                // No hurry.
                sleep(1);
                $count++;
            }
        } else {
            $logger->warning("Cannot get a message for '$item->title' and URL '$item->url'");
        }
    }
}

if ($count === 0) {
    $logger->notice('No posts');
} else {
    $logger->notice("$count posts");
}
