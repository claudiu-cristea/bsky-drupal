<?php

declare(strict_types=1);

namespace BSkyDrupal;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use potibm\Bluesky\BlueskyApi;
use potibm\Bluesky\BlueskyApiInterface;
use potibm\Bluesky\BlueskyPostService;
use potibm\Bluesky\Feed\Post;
use Psr\Log\LoggerInterface;

class App
{
    private BlueskyApiInterface $api;
    private BlueskyPostService $postService;
    private \PDO $pdo;

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function postText(string $text, string $url, \DateTimeImmutable $createdAt): void
    {
        if ($this->isUrlRegistered($url)) {
            // Already posted.
            return;
        }

        try {
            $post = Post::create($text);
            $post = $this->getPostService()->addFacetsFromMentionsAndLinksAndTags($post);
            $this->getApi()->createRecord($post);
            $this->registerUrl($url);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
            throw $exception;
        }
    }

    public function processFeed(string $feedUrl): array
    {
        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $request = $requestFactory->createRequest('GET', $feedUrl);

        $xml = Psr18ClientDiscovery::find()
          ->sendRequest($request)
          ->getBody()
          ->getContents();

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);

        $newEntries = [];
        foreach ($xpath->query('//item', $doc) as $delta => $node) {
            $url = $xpath->query('//item/link', $node)->item($delta)->textContent;
            if ($this->isUrlRegistered($url)) {
                // Already posted.
                continue;
            }
            $title = $xpath->query('//item/title', $node)->item($delta)->textContent;
            $date =  $xpath->query('//item/pubDate', $node)->item($delta)->textContent;
            $newEntries[] = [$url, $title, new \DateTimeImmutable($date)];
        }

        return array_reverse($newEntries);
    }

    private function getPostService(): BlueskyPostService
    {
        if (!isset($this->postService)) {
            $this->postService = new BlueskyPostService($this->getApi());
        }
        return $this->postService;
    }

    private function getApi(): BlueskyApi
    {
        if (!isset($this->api)) {
            $this->api = new BlueskyApi(getenv('BSKY_USER'), getenv('BSKY_PASS'));
        }
        return $this->api;
    }

    private function getConnection(): \PDO
    {
        if (!isset($this->pdo)) {
            $table = getenv('DB_TABLE');
            $this->pdo = new \PDO('sqlite:' . getenv('DB_DATABASE'));
            $sql = <<<SQL
                CREATE TABLE IF NOT EXISTS $table (
                    url VARCHAR(255) NOT NULL PRIMARY KEY,
                    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
                SQL;
            $this->pdo->query($sql);
        }
        return $this->pdo;
    }

    protected function isUrlRegistered(string $url): bool
    {
        $table = getenv('DB_TABLE');
        try {
            return (bool) $this->getConnection()->query("SELECT url FROM $table WHERE url = '$url'")->fetch();
        } catch (\Throwable $throwable) {
            $this->logger->error($throwable->getMessage());
            throw $throwable;
        }
    }

    private function registerUrl(string $url): void
    {
        $table = getenv('DB_TABLE');
        $this->getConnection()->query("INSERT INTO $table (url) VALUES ('$url')");
    }
}
