<?php

declare(strict_types=1);

namespace BSkyDrupal;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use potibm\Bluesky\BlueskyApi;
use potibm\Bluesky\BlueskyApiInterface;
use potibm\Bluesky\BlueskyPostService;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\RecordResponse;
use Psr\Log\LoggerInterface;

class App
{
    private BlueskyApiInterface $api;
    private BlueskyPostService $postService;
    private \PDO $pdo;

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function postText(string $text, string $url, \DateTimeImmutable $createdAt): bool
    {
        if ($this->isUrlRegistered($url)) {
            // Already posted.
            return false;
        }

        try {
            $post = Post::create($text);
            $post = $this->getPostService()->addFacetsFromMentionsAndLinksAndTags($post);
            $response = $this->getApi()->createRecord($post);
            $this->registerUrl($url);
            $this->success($response);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
            throw $exception;
        }

        return true;
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
            $table = getenv('BSKY_SQLITE_TABLE');
            $this->pdo = new \PDO('sqlite:' . getenv('BSKY_SQLITE_DATABASE'));
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
        $table = getenv('BSKY_SQLITE_TABLE');
        try {
            return (bool) $this->getConnection()->query("SELECT url FROM $table WHERE url = '$url'")->fetch();
        } catch (\Throwable $throwable) {
            $this->logger->error($throwable->getMessage());
            throw $throwable;
        }
    }

    private function registerUrl(string $url): void
    {
        $table = getenv('BSKY_SQLITE_TABLE');
        $this->getConnection()->query("INSERT INTO $table (url) VALUES ('$url')");
    }

    private function success(RecordResponse $response): void
    {
        $postId = $response->getUri()->getRecord();
        $user = getenv('BSKY_USER');
        $this->logger->notice("Success: https://bsky.app/profile/$user/post/$postId");
    }
}
