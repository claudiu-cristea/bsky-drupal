<?php

declare(strict_types=1);

namespace BSkyDrupal\Model;

readonly class Item
{
    public function __construct(
        public string $url,
        public string $title,
        public \DateTimeInterface $time,
    ) {
    }
}
