<?php

declare(strict_types=1);

namespace App\Amqp\Consumers;

use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Amqp\Result;

/**
 * @Consumer(exchange="tcc", routingKey="transaction-confirm", queue="confirm", nums=1)
 */
class TransactionConfirmConsumer extends ConsumerMessage
{
    public function consume($data): string
    {
        echo "消息队列执行confirm:";
        var_dump($data);

        // TODO 从redis里获取事务详情进行处理

        return Result::ACK;
    }

    public function isEnable(): bool
    {
        return config('htcc.producer_driver', 'amqp') != 'amqp' ? false : parent::isEnable();
    }
}