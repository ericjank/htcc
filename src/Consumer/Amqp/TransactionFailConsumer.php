<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Consumer\Amqp;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Result;
use Hyperf\Utils\Exception\ParallelExecutionException;
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Container\ContainerInterface;
use Hyperf\Logger\LoggerFactory;

/**
 * @Consumer(exchange="tcc", routingKey="transaction-fail", queue="transaction-cancel", nums=1)
 */
class TransactionFailConsumer extends TransactionConsumer
{
    /**
     * @Inject
     * @var ContainerInterface
     */
    public $container;

    /**
     * @Inject
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function consume($transactions): string
    {
        $logger = $this->container->get(LoggerFactory::class)->get('htcc');

        $max_retry_limit = config('htcc.max_retry_limit');
        $tid = $transactions['tid'];
        $stepts = $transactions['steps'];
        $counter = $this->recorder->getCounter($tid);
        $parallel = [];

        $this->logger->debug("消息队列执行cancel: $tid");

        foreach ($stepts as $step) 
        {
            $service = $step['service'];
            if ( ! isset($counter[$service]))
            {
                $counter[$service] = [
                    'retry_cancel_count' => 0,
                    'retry_cancel_success' => 0,
                    'retry_fail' => 0
                ];
            }

            if ($counter[$service]['retry_cancel_success'])
            {
                // 已经执行成功回滚的接口跳过
                continue;
            }

            if ($counter[$service]['retry_fail'])
            {
                $this->logger->debug(sprintf("经过 %d 次尝试事务回滚失败, 日志记录, $tid", $counter[$service]['retry_cancel_count']));
                continue;
            }

            if ((++$counter[$service]['retry_cancel_count']) >= $max_retry_limit)
            {
                // Retry fail on next time
                $counter[$service]['retry_fail'] = 1;
            }

            $parallel []= function() use ($step, $service)
            {
                $container = $this->container->get($service);
                $result = call_user_func_array([$container, $step['onCancel']], $step['params']);

                return [
                    'service' => $service,
                    'method' => $step['onCancel'],
                    'result' => $result
                ];
            };
        }

        

        if (empty($parallel))
        {
            // TODO 经过多次尝试事务回滚失败, 日志记录
            // echo sprintf("经过多次尝试事务回滚失败, 日志记录, $tid\n");
            // TODO 事务状态更新为rollbackfail
            
            return Result::DROP;
        }

        try 
        {
            $results = parallel($parallel);

            // TODO 事务状态更新为 rollbacked

            // 回滚执行成功
            return Result::ACK;
        } 
        catch (ParallelExecutionException $e) 
        {
            $results = $e->getResults(); // 获取协程中的返回值。
            // $exceptions = $e->getThrowables(); // 获取协程中出现的异常。
            
            foreach ($results as $step) 
            {
                $counter[$step['service']]['retry_cancel_success'] = 1;
            }
            
            // TODO 日志记录
            $this->logger->debug(sprintf("事务回滚失败, 日志记录, $tid"));
            // $this->logger->log($counter);

            $isLastRetry = true;
            foreach ($counter as $retry) 
            {
                if ($retry['retry_fail'] || $retry['retry_cancel_success'] || $retry['retry_cancel_count'] >= $max_retry_limit)
                {
                    continue;
                }

                $isLastRetry = false;
            }

            if ($isLastRetry)
            {
                // TODO 经过多次尝试事务回滚失败, 日志记录
                // echo sprintf("经过多次尝试事务回滚失败, 日志记录, $tid\n");
                // TODO 事务状态更新为rollbackfail
                $this->logger->debug(sprintf("经过 %d 次尝试事务回滚失败, 日志记录, $tid", $counter[$service]['retry_cancel_count']));
                return Result::DROP;
            }

            return Result::REQUEUE;
        } 
        finally 
        {
            $this->recorder->setCounter($tid, $counter);
        }

        return Result::ACK;
    }
}