<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Producer\Amqp;

use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\ProducerMessage;

/**
 * TransactionConfirmProducer
 * @Producer(exchange="tcc", routingKey="transaction-confirm")
 */
class TransactionConfirmProducer extends ProducerMessage
{
    public function __construct($message)
    {
        // 设置不同 pool
        // $this->poolName = 'pool2';

        // $user = User::where('id', $id)->first();
        $this->payload = $message;
    }
}