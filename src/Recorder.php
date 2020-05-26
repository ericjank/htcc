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
use Hyperf\Snowflake\IdGeneratorInterface;
use Hyperf\Rpc\Context as RpcContext;
use Ericjank\Htcc\Producer as TransactionProducer;

class Recorder
{
    /**
     * @Inject
     * @var RpcContext
     */
    protected $context;

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

        return TransactionProducer::confirm($tid);
    }

    public function cancel()
    {
        $tid = $this->getTransactionID();
        $this->handler->cancel($tid, $this->getSteps());

        // TODO 日志

        return TransactionProducer::cancel($tid);
    }

    public function getTransactionID()
    {
        return $this->context->get('_transaction_id') ?? 0;
    }

    public function setTransactions($transactions)
    {
        $this->context->set('_rpctransactions', $transactions);
    }

    public function getTransactions()
    {
        return $this->context->get('_rpctransactions') ?? [];
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
}
