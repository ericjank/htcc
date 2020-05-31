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

use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;
use Hyperf\Rpc\Context as RpcContext;
// use Ericjank\Htcc\Producer as TransactionProducer;
use Ericjank\Htcc\Exception\RpcTransactionException;
// use Hyperf\Utils\Coroutine;
use Ericjank\Htcc\Recorder;

class Catcher
{
    const HTCC_CATCHER_ERR = 500;

    /**
     * @Inject
     * @var RpcContext
     */
    protected $context;

    /**
     * @Inject
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @Inject
     * @var Recorder
     */
    protected $recorder;

    // private static $driver = [];
    private $handler = null;

    public function __construct()
    {
        $this->handler = $this->getInstance();
        $this->handler->recorder = $this->recorder;
    }

    public function getInstance()
    {
        // $cid = Coroutine::id();

        // if (isset(self::$driver[$cid]['driver']))
        // {
        //     return self::$driver[$cid]['driver'];
        // }

        $driver = ucfirst(config('htcc.catcher_driver', 'redis'));

        switch ($driver) {
            case 'Redis':
                return new \Ericjank\Htcc\Catcher\Redis\TransactionCatcher();
                break;
            
            default:
                # code...
                break;
        }

        // return self::$driver[$cid]['driver'];
        return null;
    }

    public function __call($method, $arguments)
    {
        if ( method_exists($this->handler, $method) ) 
        {
            $this->handler->setKey($this->recorder->getTransactionID());
            return call_user_func_array([$this->handler, $method], $arguments);
        }

        throw new RpcTransactionException(sprintf("Transaction Catcher has no method %s", $method), 4003);

    }

}
