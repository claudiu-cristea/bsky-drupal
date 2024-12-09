#!/usr/bin/env php
<?php

declare(strict_types=1);

use BSkyDrupal\App;
use BSkyDrupal\ExtensionFeedType;
use BSkyDrupal\FeedTypeInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;

require_once __DIR__ . '/vendor/autoload.php';

const FEEDS = [
  'https://www.drupal.org/changes/drupal/rss.xml' => 'change record',
  'https://www.drupal.org/security/all/rss.xml' => 'security advisory',
  'https://www.drupal.org/section-blog/2603760/feed' => 'blog',
  'https://www.drupal.org/taxonomy/term/7234/feed' => ExtensionFeedType::class,
];

$logger = (new Logger('bsky_drupal'))->pushHandler(
    new RotatingFileHandler(
        __DIR__ . '/log/bsky_drupal.log',
        3,
        Level::Debug,
        true,
        null,
        false,
        RotatingFileHandler::FILE_PER_MONTH,
    ),
);

$app = new App($logger);

foreach (FEEDS as $feedUrl => $type) {
    $count = 0;

    if (is_a($type, FeedTypeInterface::class, true)) {
        $getType = fn(string $url, string $title): ?string => $type::getMessage($url, $title);
    } elseif (is_string($type)) {
        $getType = fn(string $url, string $title): ?string => $type;
    } else {
        throw new \InvalidArgumentException('Unsupported type');
    }

    foreach ($app->processFeed($feedUrl) as [$url, $title, $date]) {
        if ($message = $getType($url, $title) ) {
            $printedDate = date('Y-m-d', $date->getTimestamp());
            $text = "#Drupal $message: $title ($printedDate). See $url";
            $app->postText($text, $url, $date);

            sleep(2);
            $count++;
        }
    }
    $logger->notice("Posted $count items ($type)");
}
