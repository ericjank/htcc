<?php

declare(strict_types=1);

namespace App\Amqp\Consumers;

use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Amqp\Result;

/**
 * @Consumer(exchange="tcc", routingKey="transaction-fail", queue="cancel", nums=1)
 */
class TransactionFailConsumer extends ConsumerMessage
{
    public function consume($data): string
    {
        echo "消息队列执行cancel:";
        var_dump($data);
        return Result::ACK;
    }

    public function isEnable(): bool
    {
        return config('htcc.producer_driver', 'amqp') != 'amqp' ? false : parent::isEnable();
    }
}