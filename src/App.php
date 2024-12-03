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
        try {
            $post = Post::create($text);
            $post = $this->getPostService()->addFacetsFromMentionsAndLinksAndTags($post);
            $post->setCreatedAt($createdAt);
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
            $host = getenv('DB_HOST');
            $db = getenv('DB_DATABASE');
            $user = getenv('DB_USER');
            $pass = getenv('DB_PASS');
            $table = getenv('DB_TABLE');

            $this->pdo = new \PDO("mysql:host=$host;dbname=$db", $user, $pass);
            if (!$this->pdo->query("SHOW TABLES LIKE '$table'")->rowCount()) {
                $create = <<<SQL
                    CREATE TABLE $table (
                      url varchar(255) NOT NULL,
                      created timestamp NOT NULL,
                      PRIMARY KEY (url),
                      KEY created (created)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                    SQL;
                $this->pdo->exec($create);
            }
        }
        return $this->pdo;
    }

    protected function isUrlRegistered(string $url): bool
    {
        $table = getenv('DB_TABLE');
        try {
            return (bool) $this->getConnection()->query("SELECT url FROM $table WHERE url = '$url'")->rowCount();
        } catch (\Throwable $throwable) {
            $this->logger->error($throwable->getMessage());
            throw $throwable;
        }
    }

    private function registerUrl(string $url): void
    {
        $table = getenv('DB_TABLE');
        $now = date('Y-m-d H:i:s');
        $this->getConnection()->query("INSERT INTO $table (url, created) VALUES ('$url', '$now')");
    }
}
