<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  50172189@qq.com
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Ericjank\Htcc;

use Hyperf\Utils\ApplicationContext;

class Producer
{
    public static function send($message, $type = 'success')
    {
        $driver = ucfirst(config('htcc.producer_driver', 'amqp'));

        switch ($driver) {
            case 'Amqp':
                if ($type == 'fail') 
                {
                    $producerMessage = new \Ericjank\Htcc\Producer\Amqp\TransactionFailProducer($message);
                }
                else if ($type == 'confirm')
                {
                    $producerMessage = new \Ericjank\Htcc\Producer\Amqp\TransactionConfirmProducer($message);
                } 
                else 
                {
                    $producerMessage = new \Ericjank\Htcc\Producer\Amqp\TransactionProducer($message);
                }
                
                $producer = ApplicationContext::getContainer()->get(\Hyperf\Amqp\Producer::class);
                return $producer->produce($producerMessage);
                break;
            
            default:
                # code...
                break;
        }

        return null;
    }

    public static function confirm($tid)
    {
        self::send($tid, 'confirm');
    }

    public static function cancel($tid)
    {
        self::send($tid, 'fail');
    }
}
