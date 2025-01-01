<?php

declare(strict_types=1);

namespace BSkyDrupal\Plugin;

use BSkyDrupal\Model\Image;
use BSkyDrupal\Model\Item;

interface SourceInterface
{

    /**
     * @param array<non-empty-string, mixed> $config
     * @return $this
     */
    public function setConfig(array $config): self;

    public function getConfigValue(string $name, mixed $default = null): mixed;

    /**
     * @return list<Item>
     */
    public function getItems(): array;

    public function getMessage(Item $item): ?string;

    public function getImage(): ?Image;
}
