<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Producer\Amqp;

use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\ProducerMessage;

/**
 * TransactionProducer
 * @Producer(exchange="tcc", routingKey="transaction")
 */
class TransactionProducer extends ProducerMessage
{
    public function __construct($message)
    {
        // 设置不同 pool
        // $this->poolName = 'pool2';

        // $user = User::where('id', $id)->first();
        $this->payload = $message;
    }
}