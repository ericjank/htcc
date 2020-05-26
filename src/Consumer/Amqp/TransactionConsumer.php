<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Consumer\Amqp;

use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Amqp\Result;

/**
 * @Consumer(exchange="tcc", routingKey="transaction", queue="transaction-normal", nums=1)
 */
class TransactionConsumer extends ConsumerMessage
{
    public function consume($data): string
    {
        echo "消息队列执行:";
        var_dump($data);
        return Result::ACK;
    }

    public function isEnable(): bool
    {
        return config('htcc.producer_driver', 'amqp') != 'amqp' ? false : parent::isEnable();
    }
}