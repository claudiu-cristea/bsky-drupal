<?php

declare(strict_types=1);

namespace BSkyDrupal\Plugin;

use BSkyDrupal\Model\Item;

class DrupalDotOrgFeed extends AbstractSource
{
    use DrupalDotOrgFeedTrait {
        validateConfig as validateConfigTrait;
    }

    public function getMessage(Item $item): ?string
    {
        $printedDate = date('Y-m-d', $item->time->getTimestamp());
        return "{$this->getConfigValue('message')}: $item->title ($printedDate) #PHP. See $item->url";
    }

    /**
     * {@inheritdoc}
     */
    protected function validateConfig(array $config): void
    {
        parent::validateConfig($config);
        $this->validateConfigTrait($config);
        $message = $config['message'] ?? null;
        if (!$message || !is_string($message)) {
            throw new \InvalidArgumentException('Missing or invalid `message` config');
        }
    }
}
