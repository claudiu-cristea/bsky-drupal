#!/usr/bin/env php
<?php

declare(strict_types=1);

use BSkyDrupal\App;
use BSkyDrupal\ExtensionReleaseFeedType;
use BSkyDrupal\FeedTypeInterface;
use BSkyDrupal\Logger\CompositeLogger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;

require_once __DIR__ . '/vendor/autoload.php';

const FEEDS = [
  'https://www.drupal.org/changes/drupal/rss.xml' => '#Drupal core change',
  'https://www.drupal.org/security/all/rss.xml' => '#Drupal security advisory',
  'https://www.drupal.org/section-blog/2603760/feed' => '#Drupal blog entry',
  'https://www.drupal.org/taxonomy/term/7234/feed' => ExtensionReleaseFeedType::class,
  'https://www.drupal.org/project/project_module/feed/full' => 'New #Drupal module',
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


$results = [];
foreach (FEEDS as $feedUrl => $type) {
    if (is_a($type, FeedTypeInterface::class, true)) {
        $getType = fn(string $url, string $title): ?string => $type::getMessage($url, $title);
    } elseif (is_string($type)) {
        $getType = fn(string $url, string $title): ?string => $type;
    } else {
        throw new \InvalidArgumentException('Unsupported type');
    }

    foreach ($app->processFeed($feedUrl) as [$url, $title, $date]) {
        if ($message = $getType($url, $title)) {
            $printedDate = date('Y-m-d', $date->getTimestamp());
            $text = "$message: $title ($printedDate). See $url";
            $app->postText($text, $url, $date);

            // No hurry.
            sleep(1);

            $results[$message] ??= 0;
            $results[$message]++;
        } else {
            $logger->warning("Cannot get a message for $feedUrl ($type) with title '$title' and URL '$url'");
        }
    }
}

$posted = implode(', ', array_map(
    fn(string $message): string => "$message ($results[$message])",
    array_keys($results),
));

if (empty($posted)) {
    $logger->notice('No posts');
} else {
    $logger->notice("Posted: $posted");
}
