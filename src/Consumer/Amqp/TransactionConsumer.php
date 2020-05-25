<?php

declare(strict_types=1);

namespace App\Amqp\Consumers;

use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Amqp\Result;

/**
 * @Consumer(exchange="tcc", routingKey="transaction", queue="normal", nums=1)
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