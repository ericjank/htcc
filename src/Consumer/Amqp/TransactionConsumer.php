<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Consumer\Amqp;

use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;
use Hyperf\Amqp\Message\ConsumerMessage;
use Ericjank\Htcc\Recorder;

class TransactionConsumer extends ConsumerMessage
{
    /**
     * @Inject
     * @var ContainerInterface
     */
    public $container;

    /**
     * @Inject
     * @var Recorder
     */
    public $recorder;

    public function isEnable(): bool
    {
        return config('htcc.producer_driver', 'amqp') != 'amqp' ? false : parent::isEnable();
    }
}