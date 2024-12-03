#!/usr/bin/env php
<?php

declare(strict_types=1);

use BSkyDrupal\App;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

require_once __DIR__ . '/vendor/autoload.php';

const FEEDS = [
  'https://www.drupal.org/changes/drupal/rss.xml' => 'change record',
  'https://www.drupal.org/security/all/rss.xml' => 'security advisory',
  'https://www.drupal.org/section-blog/2603760/feed' => 'blog',
];

$logger = (new Logger('bsky_drupal'))->pushHandler(
    new RotatingFileHandler(__DIR__ . '/log/bsky_drupal.log'),
);

$app = new App($logger);

foreach (FEEDS as $feedUrl => $type) {
    $count = 0;
    foreach ($app->processFeed($feedUrl) as [$url, $title, $date]) {
        $printedDate = date('Y-m-d', $date->getTimestamp());
        $app->postText(
            "#Drupal $type: $title ($printedDate). See $url",
            $url,
            $date
        );
        sleep(2);
        $count++;
    }
    print "Posted $count items ($type)\n";
}
