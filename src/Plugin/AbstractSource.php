<?php

declare(strict_types=1);

namespace BSkyDrupal\Plugin;

use BSkyDrupal\Model\Image;
use Psr\Log\LoggerInterface;

abstract class AbstractSource implements SourceInterface
{
    /**
     * @var array<non-empty-string, mixed>
     */
    private array $config = [];

    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function setConfig(array $config): SourceInterface
    {
        $this->validateConfig($config);
        $this->config = $config;
        return $this;
    }

    public function getConfigValue(string $name, mixed $default = null): mixed
    {
        return $this->config[$name] ?? $default;
    }

    public function getImage(): ?Image
    {
        if ($image = $this->getConfigValue('image')) {
            return new Image($image['file'], $image['alt']);
        }
        return null;
    }

    protected function logException(\Throwable $exception): void
    {
        $message = sprintf(
            '[%s] %s (%s): %s',
            $exception->getCode(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getMessage(),
        );
        $this->logger->error($message);
    }

    /**
     * @param array<non-empty-string, mixed> $config
     */
    protected function validateConfig(array $config): void
    {
        if (!array_key_exists('image', $config)) {
            return;
        }
        if (!is_array($config['image']) || (empty($config['image']['file']) || empty($config['image']['alt']))) {
            throw new \InvalidArgumentException(
                'The `image` config should be an array with two non-empty keys: `file` and `alt`'
            );
        }
        if (!file_exists($config['image']['file']) || !is_readable($config['image']['file'])) {
            throw new \InvalidArgumentException("The `image.file` config file doesn't exist or is not readable");
        }
        if (!is_string($config['image']['alt'])) {
            throw new \InvalidArgumentException("The `image.alt` config should be a string");
        }
    }
}
