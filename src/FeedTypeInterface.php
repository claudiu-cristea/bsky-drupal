<?php

declare(strict_types=1);

namespace BSkyDrupal;

interface FeedTypeInterface
{
    public static function getMessage(string $url, string $title): ?string;
}
