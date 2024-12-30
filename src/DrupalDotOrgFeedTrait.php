<?php

declare(strict_types=1);

namespace BSkyDrupal;

use BSkyDrupal\Model\Item;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;

trait DrupalDotOrgFeedTrait
{
    /**
     * {@inheritdoc}
     */
    public function getItems(): array
    {
        $feedUrl = $this->getConfig()['feed_url'] ?? null;
        if (!$feedUrl || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Missing or invalid `feed_url` config');
        }

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
            $title = $xpath->query('//item/title', $node)->item($delta)->textContent;
            $date =  $xpath->query('//item/pubDate', $node)->item($delta)->textContent;
            $newEntries[] = new Item($url, $title, new \DateTimeImmutable($date));
        }

        return array_reverse($newEntries);
    }
}
