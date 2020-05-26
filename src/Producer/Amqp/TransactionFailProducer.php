<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Producer\Amqp;

use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\ProducerMessage;

/**
 * TransactionFailProducer
 * @Producer(exchange="tcc", routingKey="transaction-fail")
 */
class TransactionFailProducer extends ProducerMessage
{
    public function __construct($message)
    {
        // 设置不同 pool
        // $this->poolName = 'pool2';

        // $user = User::where('id', $id)->first();
        $this->payload = $message;
    }
}