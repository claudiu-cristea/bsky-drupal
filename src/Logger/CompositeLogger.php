<?php

declare(strict_types=1);

namespace BSkyDrupal\Logger;

use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

class CompositeLogger extends AbstractLogger
{
    private LoggerInterface $infoLogger;
    private LoggerInterface $errorLogger;

    public function __construct(
        string $name,
        HandlerInterface $commonHandler,
        HandlerInterface $infoHandler,
        HandlerInterface $errorHandler,
    ) {
        $this->infoLogger = (new Logger($name))
            ->pushHandler($commonHandler)
            ->pushHandler($infoHandler);
        $this->errorLogger = (new Logger($name))
            ->pushHandler($commonHandler)
            ->pushHandler($errorHandler);
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        if ($level <= Level::Notice->value) {
            $this->infoLogger->log($level, $message, $context);
        } else {
            $this->errorLogger->log($level, $message, $context);
        }
    }
}
