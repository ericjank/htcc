<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Consumer\Amqp;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Amqp\Result;
use Hyperf\Redis\Redis;

/**
 * @Consumer(exchange="tcc", routingKey="transaction-confirm", queue="transaction-confirm", nums=1)
 */
class TransactionConfirmConsumer extends ConsumerMessage
{
    /**
     * @Inject
     * @var Redis
     */
    private $redis;

    public function consume($tid): string
    {
        echo "消息队列执行confirm:";
        var_dump($tid);

        // TODO 从redis里获取事务详情进行处理
        $transaction = $this->redis->hget('Htcc', $tid);
        var_dump(json_decode($transaction, true));

        return Result::NACK;
    }

    public function isEnable(): bool
    {
        return config('htcc.producer_driver', 'amqp') != 'amqp' ? false : parent::isEnable();
    }
}