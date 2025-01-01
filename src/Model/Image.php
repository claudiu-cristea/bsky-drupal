<?php

declare(strict_types=1);

namespace BSkyDrupal\Model;

readonly class Image
{
    public function __construct(
        public string $file,
        public string $alt,
    ) {
    }
}
