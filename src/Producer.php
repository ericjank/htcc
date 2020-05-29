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
use Ericjank\Htcc\Exception\RpcTransactionException;

class Producer
{
    public static $drivers = [];

    public static function getInstance()
    {
        $driver = ucfirst(config('htcc.producer_driver', 'amqp'));

        if (isset(self::$drivers[$driver]))
        {
            return self::$drivers[$driver];
        }

        switch ($driver) 
        {
            case 'Amqp':
                self::$drivers[$driver] = ApplicationContext::getContainer()->get(\Hyperf\Amqp\Producer::class);
                return self::$drivers[$driver];
            
            default:
                break;
        }

        return null;
    }

    public static function getMessager($message, $type = 'success')
    {
        $driver = ucfirst(config('htcc.producer_driver', 'amqp'));

        switch ($driver) 
        {
            case 'Amqp':
                if ($type == 'fail') 
                {
                    return new \Ericjank\Htcc\Producer\Amqp\TransactionFailProducer($message);
                }
                else if ($type == 'confirm')
                {
                    return new \Ericjank\Htcc\Producer\Amqp\TransactionConfirmProducer($message);
                }

                return new \Ericjank\Htcc\Producer\Amqp\TransactionProducer($message);

            default:
                break;
        }

        return null;
    }

    public static function send($message, $type = 'success')
    {
        $producer = self::getInstance();

        if ( ! empty($producer))
        {
            $messager = self::getMessager($message, $type);
            return $producer->produce($messager);
        }

        throw new RpcTransactionException(sprintf("Htcc producer driver %s not found.", config('htcc.producer_driver', 'amqp')), 4002);
    }

    public static function confirm($tid)
    {
        return self::send($tid, 'confirm');
    }

    public static function cancel($tid)
    {
        return self::send($tid, 'fail');
    }
}
