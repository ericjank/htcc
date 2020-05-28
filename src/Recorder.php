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
use Hyperf\Snowflake\IdGeneratorInterface;
use Hyperf\Rpc\Context as RpcContext;
use Ericjank\Htcc\Producer as TransactionProducer;
use Ericjank\Htcc\Exception\RpcTransactionException;

class Recorder
{
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
     * @var IdGeneratorInterface
     */
    protected $idGenerator;

    private $handler = null;

    public function __construct()
    {
        $driver = ucfirst(config('htcc.recorder_driver', 'redis'));

        switch ($driver) {
            case 'Redis':
                $this->handler = new \Ericjank\Htcc\Recorder\Redis\TransactionRecorder();
                break;
            
            default:
                # code...
                break;
        }
    }

    public function __call($method, $arguments)
    {
        if ( method_exists($this->handler, $method) ) 
        {
            return call_user_func_array([$this->handler, $method], $arguments);
        }
    }

    public function getTransactionByID($tid)
    {
        return $this->handler->get($tid);
    }

    public function startTransaction($annotation)
    {
        $tid = (string)$this->idGenerator->generate();
        $this->context->set('_rpctransaction_started', true);
        $this->handler->add($tid, $annotation);
        $this->context->set('_transaction_id', $tid);

        // TODO 日志

        return $tid;
    }

    public function confirm()
    {
        $tid = $this->getTransactionID();
        $this->handler->confirm($tid, $this->getSteps());

        // TODO 日志

        // TODO 确保消息投递成功

        return TransactionProducer::confirm(['tid' => $tid, 'steps' => $this->getSteps()]);
    }

    public function cancel()
    {
        $tid = $this->getTransactionID();
        $this->handler->cancel($tid, $this->getSteps());

        // TODO 日志

        // TODO 确保消息投递成功

        return TransactionProducer::cancel(['tid' => $tid, 'steps' => $this->getSteps()]);
    }

    public function getTransactionID()
    {
        return $this->context->get('_transaction_id') ?? 0;
    }

    public function setTransactions($transactions)
    {
        $tid = $this->getTransactionID();

        if (! isset($transactions[$tid]))
        {
            throw new RpcTransactionException("Can not find transaction", 4001);
        }

        foreach ($transactions[$tid] as $proxyClass => $server) {
            $this->checkMethodExists($server['service'], [
                'try' => $server['try'], 
                'confirm' => $server['onConfirm'], 
                'cancel' => $server['onCancel']
            ]);
        }

        $this->context->set('_rpctransactions', $transactions);
    }

    public function getTransactions($tid = null)
    {
        $transactions = $this->context->get('_rpctransactions') ?? [];
        return is_null($tid) ? $transactions : [ 'tid' => $tid, 'transactions' => $transactions[$tid] ];
    }

    public function addStep($transaction) 
    {
        $steps = $this->context->get('_rpctransaction_steps') ?? [];
        $steps []= $transaction;
        $this->context->set('_rpctransaction_steps', $steps);

        return $transaction;
    }

    public function getSteps()
    {
        return $this->context->get('_rpctransaction_steps') ?? [];
    }

    public function isStarted()
    {
        return $this->context->get('_rpctransaction_started') ? true : false;
    }

    public function getErrorMessage()
    {
        return $this->context->get('_rpcclienterror') ?? null;
    }

    public function setError($transaction)
    {
        return $this->context->set('_rpctransaction_error', $transaction);
    }

    public function getError()
    {
        return $this->context->get('_rpctransaction_error') ?? null;
    }

    private function checkMethodExists($className, $methods)
    {
        $container = $this->container->get($className);

        if ( ! $container)
        {
            throw new RpcTransactionException(sprintf("Can not find service `%s`", $className), 4001);
        }

        if ( ! is_array($methods))
        {
            $methods = [$methods];
        }

        foreach ($methods as $action => $method) 
        {
            if ( ! method_exists($container, $method))
            {
                throw new RpcTransactionException(sprintf("[Rpc Transaction]Can not find %s method `%s` for service `%s`, please check your `Compensable` annotation", $action, $method, $className), 4001);
            }
        }
    }
}
