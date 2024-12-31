<?php

declare(strict_types=1);

namespace BSkyDrupal\Plugin;

use BSkyDrupal\AbstractSource;
use BSkyDrupal\DrupalDotOrgFeedTrait;
use BSkyDrupal\Model\Item;
use Github\AuthMethod;
use Github\Client;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Symfony\Component\Yaml\Yaml;

class DDev extends AbstractSource
{
    /**
     * {@inheritdoc}
     */
    public function getItems(): array
    {
        $httpClient = Psr18ClientDiscovery::find();
        $client = Client::createWithHttpClient($httpClient);
        $client->authenticate(getenv('BSKY_GITHUB_TOKEN'), AuthMethod::ACCESS_TOKEN);
        $release = $client->api('repo')->releases()->latest('ddev', 'ddev');
        return [
            new Item(
                $release['html_url'],
                $release['tag_name'],
                new \DateTimeImmutable($release['created_at']),
            ),
        ];
    }

    public function getMessage(Item $item): ?string
    {
        $printedDate = date('Y-m-d', $item->time->getTimestamp());
        return "New @ddev.bsky.social release: $item->title ($printedDate) #DDEV. See $item->url";
    }
}
