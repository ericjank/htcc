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
    public static $messager = [];

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

                if ( isset(self::$messager[$type]))
                {
                    // TODO 检查内存泄露
                    $messager = clone self::$messager[$type];
                    $messager->setPayload($message);
                    return $messager;
                }
                else 
                {
                    if ($type == 'fail') 
                    {
                        self::$messager[$type] = new \Ericjank\Htcc\Producer\Amqp\TransactionFailProducer($message);
                        return self::$messager[$type];
                    }
                    else if ($type == 'confirm')
                    {
                        self::$messager[$type] = new \Ericjank\Htcc\Producer\Amqp\TransactionConfirmProducer($message);
                        return self::$messager[$type];
                    }
                }

                self::$messager[$type] = new \Ericjank\Htcc\Producer\Amqp\TransactionProducer($message);
                return self::$messager[$type];
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
            return $producer->produce( self::getMessager($message, $type) );
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
