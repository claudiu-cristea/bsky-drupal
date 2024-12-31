<?php

declare(strict_types=1);

namespace BSkyDrupal\Plugin;

use BSkyDrupal\AbstractSource;
use BSkyDrupal\Model\Item;
use Github\Api\Repo;
use Github\AuthMethod;
use Github\Client;
use Http\Discovery\Psr18ClientDiscovery;

class GitHubRepoLatestRelease extends AbstractSource
{
    /**
     * {@inheritdoc}
     */
    public function getItems(): array
    {
        if (!$namespace = $this->getConfigValue('namespace')) {
            throw new \InvalidArgumentException('Missing or invalid `namespace` config');
        }
        if (!$project = $this->getConfigValue('project')) {
            throw new \InvalidArgumentException('Missing or invalid `project` config');
        }

        try {
            $httpClient = Psr18ClientDiscovery::find();
            $client = Client::createWithHttpClient($httpClient);
            $client->authenticate(getenv('BSKY_GITHUB_TOKEN'), AuthMethod::ACCESS_TOKEN);
            $repo = $client->api('repo');
            \assert($repo instanceof Repo);
            $release = $repo->releases()->latest($namespace, $project);
            return [
              new Item(
                  $release['html_url'],
                  $release['tag_name'],
                  new \DateTimeImmutable($release['created_at']),
              ),
            ];
        } catch (\Throwable $exception) {
            $this->logException($exception);
            return [];
        }
    }

    public function getMessage(Item $item): ?string
    {
        if ($pattern = $this->getConfigValue('pattern')) {
            $printedDate = date('Y-m-d', $item->time->getTimestamp());
            return sprintf($pattern, $item->title, $printedDate, $item->url);
        }
        throw new \InvalidArgumentException('Missing or invalid `pattern` config');
    }
}
