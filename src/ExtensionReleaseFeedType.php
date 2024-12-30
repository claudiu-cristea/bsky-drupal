<?php

declare(strict_types=1);

namespace BSkyDrupal;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Symfony\Component\Yaml\Yaml;

class ExtensionReleaseFeedType implements FeedTypeInterface
{
    private const string CODE_URL = 'http://drupalcode.org/project';

    public static function getMessage(string $url, string $title): ?string
    {
        $httpClient = Psr18ClientDiscovery::find();
        $uriFactory = Psr17FactoryDiscovery::findUriFactory();
        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();

        // Get project name.
        $path = trim(parse_url($url, PHP_URL_PATH), '/');
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

        return $extensionType ? "$extensionType new release" : null;
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
