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

namespace Ericjank\Htcc\Aspect;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\RpcClient\ProxyFactory;
use Hyperf\Rpc\Context as RpcContext;
use Ericjank\Htcc\Annotation\Compensable;
use Ericjank\Htcc\Recorder as TransactionRecorder;

/**
 * @Aspect
 */
class CompensableAnnotationAspect extends AbstractAspect
{
    /**
     * @Inject
     * @var TransactionRecorder
     */
    protected $recorder;

    /**
     * @Inject
     * @var RpcContext
     */
    protected $rpcContext;

    public $classes = [];

    public $annotations = [
        Compensable::class,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $annotation = $this->getAnnotations($proceedingJoinPoint);

        if ( ! $started = $this->recorder->isStarted()) {
            $transaction_id = $this->recorder->startTransaction($annotation->toArray());

            $master = $this->recorder->addStep([
                'service' => $proceedingJoinPoint->className,
                'try' => $proceedingJoinPoint->methodName,
                'onConfirm' => $annotation->onConfirm,
                'onCancel' => $annotation->onCancel,
            ]);

            $transactions[$transaction_id] = [
                $proceedingJoinPoint->className => $master
            ];

            if ( !empty($annotation->clients)) {
                foreach ($annotation->clients as $key => $value) {
                    $value['proxy'] = ProxyFactory::get($value['service']);
                    $transactions[$transaction_id][$value['proxy']] = $value;
                }
            }

            $this->recorder->setTransactions($transactions);
            

            // TODO 如启动了事务
            // 第一阶段执行完毕后 执行第二阶段提交或回滚
            // 所有try均执行完成后进入第二阶段, 要避免空悬挂
            // 协程终止时检测各个RPC接口的状态, 如有异常则执行各个已执行接口的回滚事务, 如无异常则执行各个接口的confirm
            defer(function () {
                // TODO 如果为调试阶段 检查各个接口是否均实现了cancel和confirm方法, 如未实现则在控制台提示

                $transaction_id = $this->recorder->getTransactionID();

                $error = $this->recorder->getError();
                var_dump("error", $error);

                // 异常处理器捕获到的异常
                $rpcClientError = $this->recorder->getErrorMessage();

                if ( ! empty($rpcClientError)) 
                {
                    // TODO 如果接口报错则进行回滚
                    // 接口如果报错则存储到队列中, 准备回滚

                    // foreach ($transactionSteps as $key => $value) {
                    //     TransactionProducer::send([
                    //         'tid' => $transaction_id, 
                    //         'transaction', $value
                    //     ], 'fail');
                    // }
                    echo "执行了事务: $transaction_id, 失败回滚, 提交到队列\n";
                }
                else {
                    echo "执行了事务: $transaction_id, 成功开始第二阶段, 提交到队列\n";
                }

                $action = ( ! empty($rpcClientError)) ? 'cancel' : 'confirm';
                $this->recorder->$action();

                // TODO 释放资源

            });

            // $res = $proceedingJoinPoint->process();
            // $master['result'] = $res;

            // return $res;
        }

        return $proceedingJoinPoint->process();
    }

    public function getAnnotations(ProceedingJoinPoint $proceedingJoinPoint): Compensable
    {
        $metadata = $proceedingJoinPoint->getAnnotationMetadata();
        return $metadata->method[Compensable::class] ?? null;
    }
}