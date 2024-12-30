<?php

declare(strict_types=1);

namespace BSkyDrupal\Plugin;

use BSkyDrupal\AbstractSource;
use BSkyDrupal\DrupalDotOrgFeedTrait;
use BSkyDrupal\Model\Item;

class DrupalDotOrgFeed extends AbstractSource
{
    use DrupalDotOrgFeedTrait;

    public function getMessage(Item $item): ?string
    {
        $message = $this->getConfig()['message'] ?? null;
        if (!$message) {
            throw new \InvalidArgumentException('Missing or invalid `message` config');
        }
        $printedDate = date('Y-m-d', $item->time->getTimestamp());
        return "$message: $item->title ($printedDate). See $item->url";
    }
}
