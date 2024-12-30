<?php

declare(strict_types=1);

namespace BSkyDrupal;

use BSkyDrupal\Model\Item;

interface SourceInterface
{
    public function setConfig(array $config): self;

    public function getConfig(): array;

    /**
     * @return list<Item>
     */
    public function getItems(): array;

    public function getMessage(Item $item): ?string;
}
