<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Consumer\Amqp;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Result;
use Hyperf\Utils\Exception\ParallelExecutionException;

/**
 * @Consumer(exchange="tcc", routingKey="transaction-fail", queue="transaction-cancel", nums=1)
 */
class TransactionFailConsumer extends TransactionConsumer
{
    public $action = 'cancel';
    public $message = "事务回滚失败, transaction id: %s";
    public $retrymessage = "接口 %s->%s 经过 %d 次尝试事务回滚失败, %s";
    public $lastmessage = "经过 %d 次尝试事务回滚失败, Transaction ID: %s";

    public function consume($transactions): string
    {
        $this->tid     = $transactions['tid'];
        $this->stepts  = $transactions['steps'];
        $this->counter = $this->recorder->getCounter($this->tid);
        
        $parallel = $this->getParallels();

        if (empty($parallel))
        {
            $this->onLastRetry();
            return Result::DROP;
        }

        try 
        {
            $results = parallel($parallel);

            foreach ($results as $step) 
            {
                $this->counter[$step['service']]['retry_success'] = 1;
            }

            $this->recorder->rollbackSuccess($this->tid, $this->counter);

            return Result::ACK;
        } 
        catch (ParallelExecutionException $e) 
        {
            $results = $e->getResults(); // 获取协程中的返回值。
            
            foreach ($results as $step) 
            {
                $this->counter[$step['service']]['retry_success'] = 1;
            }
            
            $this->outlogger->error(sprintf("Transaction ID: %s, Exceptions: ", $this->tid), $e->getThrowables());

            if ( $this->isLastRetry())
            {
                $this->onLastRetry();
                return Result::DROP;
            }

            // TODO 间隔几秒后重新入队
            return Result::REQUEUE;
        } 
        finally 
        {
            if ( ! $this->isLastRetry()) 
            {
                $this->recorder->setCounter($this->tid, $this->counter);
            }
        }

        return Result::ACK;
    }
}