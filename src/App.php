<?php

declare(strict_types=1);

namespace BSkyDrupal;

use BSkyDrupal\Model\Image;
use BSkyDrupal\Plugin\SourceInterface;
use potibm\Bluesky\BlueskyApi;
use potibm\Bluesky\BlueskyApiInterface;
use potibm\Bluesky\BlueskyPostService;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\RecordResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

class App
{
    private BlueskyApiInterface $api;

    private BlueskyPostService $postService;

    private \PDO $pdo;

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function run(): int
    {
        $count = 0;
        foreach ($this->getPlugins() as $plugin) {
            foreach ($plugin->getItems() as $item) {
                if (!$message = $plugin->getMessage($item)) {
                    $this->logger->warning("Cannot get a message for '$item->title' and URL '$item->url'");
                    return 1;
                }

                $image = $plugin->getImage();
                if ($response = $this->post($message, $item->url, $item->time, $image)) {
                    $this->registerUrl($item->url);
                    $this->success($response);
                    $count++;

                    // No hurry.
                    sleep(1);
                }
            }
        }

        if ($count === 0) {
            $this->logger->notice('No posts');
        } else {
            $this->logger->notice("$count posts");
        }
        return 0;
    }

    private function post(
        string $text,
        string $url,
        \DateTimeInterface $createdAt,
        ?Image $image = null,
    ): RecordResponse|false {
        if ($this->isUrlRegistered($url)) {
            // Already posted.
            return false;
        }

        try {
            $post = Post::create($text);
            $post = $this->getPostService()->addFacetsFromMentionsAndLinksAndTags($post);
            if ($image) {
                $post = $this->getPostService()->addImage($post, $image->file, $image->alt);
            }
            return $this->getApi()->createRecord($post);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
            throw $exception;
        }
    }

    private function registerUrl(string $url): void
    {
        $table = getenv('BSKY_SQLITE_TABLE');
        $this->getConnection()
          ->query("INSERT INTO $table (url) VALUES ('$url')");
    }

    private function success(RecordResponse $response): void
    {
        $postId = $response->getUri()->getRecord();
        $user = getenv('BSKY_USER');
        $this->logger->notice("Success: https://bsky.app/profile/$user/post/$postId");
    }

    private function getPostService(): BlueskyPostService
    {
        if (!isset($this->postService)) {
            // @todo Simplify when https://github.com/potibm/phluesky/pull/39 lands.
            $api = $this->getApi();
            \assert($api instanceof BlueskyApi);
            $this->postService = new BlueskyPostService($api);
        }
        return $this->postService;
    }

    private function getApi(): BlueskyApiInterface
    {
        if (!isset($this->api)) {
            $this->api = new BlueskyApi(
                (string)getenv('BSKY_USER'),
                (string)getenv('BSKY_PASS'),
            );
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

    private function isUrlRegistered(string $url): bool
    {
        $table = getenv('BSKY_SQLITE_TABLE');
        try {
            return (bool)$this->getConnection()
              ->query("SELECT url FROM $table WHERE url = '$url'")
              ->fetch();
        } catch (\Throwable $throwable) {
            $this->logger->error($throwable->getMessage());
            throw $throwable;
        }
    }

    /**
     * @return iterable<SourceInterface>
     */
    private function getPlugins(): iterable
    {
        $definitions = Yaml::parse(file_get_contents(__DIR__ . '/../plugin_definitions.yml'))['definitions'] ?? [];
        foreach ($definitions as $definition) {
            $plugin = new $definition['class']($this->logger);
            \assert($plugin instanceof SourceInterface);
            $plugin->setConfig($definition['config'] ?? []);
            yield $plugin;
        }
    }
}
