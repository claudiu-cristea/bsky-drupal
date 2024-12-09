<?php

declare(strict_types=1);

namespace BSkyDrupal;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Symfony\Component\Yaml\Yaml;

class ExtensionFeedType implements FeedTypeInterface
{
    private const string CODE_URL = 'http://drupalcode.org/project';

    public static function getMessage(string $url, string $title): ?string
    {
        $httpClient = Psr18ClientDiscovery::find();
        $uriFactory = Psr17FactoryDiscovery::findUriFactory();
        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();

        // Get project name.
        $parts = explode('/', $url);
        $projectName = $parts[count($parts) - 3];
        $uri = $uriFactory->createUri(self::CODE_URL . "/$projectName/-/raw/HEAD/$projectName.info.yml");

        $request = $requestFactory->createRequest('GET', $uri);
        $request->withHeader('Accept', 'application/json');
        $response = $httpClient->sendRequest($request);
        $info = Yaml::parse($response->getBody()->getContents());

        $extensionType ??= $info['type'];

        return $extensionType ? "new $extensionType release" : null;
    }
}
