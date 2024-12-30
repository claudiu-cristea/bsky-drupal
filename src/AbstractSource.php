<?php

declare(strict_types=1);

namespace BSkyDrupal;

abstract class AbstractSource implements SourceInterface
{
    private array $config = [];

    public function setConfig(array $config): SourceInterface
    {
        $this->config = $config;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
