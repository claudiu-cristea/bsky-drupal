<?php

declare(strict_types=1);

namespace BSkyDrupal\Plugin;

use BSkyDrupal\Model\Item;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;

class DrupalDotOrgRestApi7 extends AbstractSource
{
    private const array TYPES = [
      'drupal-module' => 'module',
      'drupal-profile' => 'distribution',
      'drupal-recipe' => 'recipe',
      'drupal-theme' => 'theme',
    ];

    public function getItems(): array
    {
        $httpClient = Psr18ClientDiscovery::find();
        $uriFactory = Psr17FactoryDiscovery::findUriFactory();
        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();

        $url = $uriFactory->createUri(http_build_url([
          'scheme' => 'https',
          'host' => 'www.drupal.org',
          'path' => 'api-d7/node.json',
          'query' => http_build_query([
            'type' => $this->getConfigValue('type'),
            'sort' => $this->getConfigValue('sort'),
            'direction' => strtoupper($this->getConfigValue('direction')),
            'limit' => $this->getConfigValue('limit'),
          ]),
        ]));

        try {
            $request = $requestFactory->createRequest('GET', $url);
            $response = $httpClient->sendRequest($request);
            $list = json_decode($response->getBody()->getContents(), true)['list'];
        } catch (\Throwable $exception) {
            $this->logException($exception);
            return [];
        }

        $items = [];
        foreach ($list as $item) {
            if (!$extensionType = $this->getExtensionType($item)) {
                continue;
            }
            $date = new \DateTimeImmutable("@{$item['created']}");
            $items[] = new Item($item['url'], $item['title'], $date, [
              'extensionType' => $extensionType,
            ]);
        }

        return $items;
    }

    public function getMessage(Item $item): ?string
    {
        $printedDate = date('Y-m-d', $item->time->getTimestamp());
        $extensionType = $item->metadata['extensionType'];
        return "#Drupal $extensionType release: $item->title ($printedDate) #PHP. See $item->url";
    }

    /**
     * @param array{field_composer_type: string} $item
     */
    private function getExtensionType(array $item): ?string
    {
        if (empty($item['field_composer_type'])) {
            return null;
        }
        if (!isset(self::TYPES[$item['field_composer_type']])) {
            return null;
        }
        return self::TYPES[$item['field_composer_type']];
    }

    protected function validateConfig(array $config): void
    {
        if (empty($config['sort'])) {
            throw new \InvalidArgumentException("The 'sort' config is missing");
        }

        $directions = ['asc', 'desc'];
        $direction = $config['direction'] ?? null;
        if (!$direction || !in_array(strtolower($direction), $directions, true)) {
            throw new \InvalidArgumentException("The 'direction' config is missing or is invalid");
        }

        if (empty($config['type'])) {
            throw new \InvalidArgumentException("The 'type' config is missing");
        }

        if (empty($config['limit']) || !is_numeric($config['limit']) || (int)$config['limit'] <= 0) {
            throw new \InvalidArgumentException("The 'limit' config is invalid");
        }
    }
}
