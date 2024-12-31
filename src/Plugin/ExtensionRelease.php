<?php

declare(strict_types=1);

namespace BSkyDrupal\Plugin;

use BSkyDrupal\AbstractSource;
use BSkyDrupal\DrupalDotOrgFeedTrait;
use BSkyDrupal\Model\Item;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Symfony\Component\Yaml\Yaml;

class ExtensionRelease extends AbstractSource
{
    use DrupalDotOrgFeedTrait;

    private const string CODE_URL = 'http://drupalcode.org/project';

    public function getMessage(Item $item): ?string
    {
        $httpClient = Psr18ClientDiscovery::find();
        $uriFactory = Psr17FactoryDiscovery::findUriFactory();
        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();

        // Get project name.
        $path = trim(parse_url($item->url, PHP_URL_PATH), '/');
        [/* project */, $name, /* release */, $version] = explode('/', $path);
        $version = !str_ends_with($version, '-dev') ? $version : substr($version, 0, -4);

        $extensionName = static::getExtensionName($name);
        $uri = $uriFactory->createUri(self::CODE_URL . "/$name/-/raw/$version/$extensionName.info.yml");

        try {
            $request = $requestFactory->createRequest('GET', $uri);
            $request->withHeader('Accept', 'application/json');
            $response = $httpClient->sendRequest($request);
            $info = Yaml::parse($response->getBody()->getContents());
        } catch (\Exception $e) {
            return null;
        }

        $extensionType = $info['type'] ?? null;

        if (!$extensionType) {
            return null;
        }

        $printedDate = date('Y-m-d', $item->time->getTimestamp());
        return "#Drupal $extensionType release: $item->title ($printedDate) #PHP. See $item->url";
    }

    protected static function getExtensionName(string $name): string
    {
        // Apply alterations.
        return match ($name) {
            'domain_google_analytics' => 'multidomain_google_analytics',
            default => $name,
        };
    }
}
