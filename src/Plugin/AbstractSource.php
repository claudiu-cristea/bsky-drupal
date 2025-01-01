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
    abstract protected function validateConfig(array $config): void;
}
