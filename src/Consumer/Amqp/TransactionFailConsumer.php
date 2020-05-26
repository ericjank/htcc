<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Consumer\Amqp;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Amqp\Result;
use Hyperf\Redis\Redis;

/**
 * @Consumer(exchange="tcc", routingKey="transaction-fail", queue="transaction-cancel", nums=1)
 */
class TransactionFailConsumer extends ConsumerMessage
{
    /**
     * @Inject
     * @var Redis
     */
    private $redis;

    public function consume($tid): string
    {
        echo "消息队列执行cancel:";
        var_dump($tid);

        // TODO 从redis里获取事务详情进行处理
        $transaction = $this->redis->hget('Htcc', $tid);
        var_dump(json_decode($transaction, true));

        return Result::ACK;
    }

    public function isEnable(): bool
    {
        return config('htcc.producer_driver', 'amqp') != 'amqp' ? false : parent::isEnable();
    }
}