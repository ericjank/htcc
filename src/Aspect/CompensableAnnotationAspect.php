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
use Ericjank\Htcc\Exception\RpcTransactionException;

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
     * @var ProxyFactory
     */
    protected $factory;

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
                'service'   => $proceedingJoinPoint->className,
                'try'       => $proceedingJoinPoint->methodName,
                'onConfirm' => (isset($annotation->onConfirm) && ! empty($annotation->onConfirm)) ? 
                    $annotation->onConfirm : $proceedingJoinPoint->methodName . 'Confirm',
                'onCancel'  => (isset($annotation->onCancel) && ! empty($annotation->onCancel)) ? 
                    $annotation->onCancel : $proceedingJoinPoint->methodName . 'Cancel',
                'params'    => $proceedingJoinPoint->getArguments()
            ]);

            $transactions[$transaction_id] = [
                $proceedingJoinPoint->className => $master
            ];

            if ( !empty($annotation->clients)) 
            {
                foreach ($annotation->clients as $key => $value) 
                {
                    $value['proxy'] = ProxyFactory::get($value['service']);
                    if ( empty($value['proxy']))
                    {
                        $this->factory->createProxy($value['service']);
                        $value['proxy'] = ProxyFactory::get($value['service']);

                        if ( empty($value['proxy']))
                        {
                            throw new RpcTransactionException(sprintf("Can not find proxy class for %s", $value['service']), 1);
                        }
                    }
                    $transactions[$transaction_id][$value['proxy']] = $value;
                }
            }

            $this->recorder->setTransactions($transactions);
            

            // 第一阶段执行完毕后 执行第二阶段提交或回滚
            // 所有try均执行完成后进入第二阶段, 要避免空悬挂
            // 协程终止时检测各个RPC接口的状态, 如有异常则执行各个已执行接口的回滚事务, 如无异常则执行各个接口的confirm
            defer(function () {
                $transaction_id = $this->recorder->getTransactionID();

                // $error = $this->recorder->getError();

                $rpcError = $this->recorder->getErrorMessage();

                $action = ( ! empty($rpcError)) ? 'cancel' : 'confirm';
                $this->recorder->$action();

                // TODO 释放资源
            });
        }

        $res = $proceedingJoinPoint->process();

        return $res;
    }

    public function getAnnotations(ProceedingJoinPoint $proceedingJoinPoint): Compensable
    {
        $metadata = $proceedingJoinPoint->getAnnotationMetadata();
        return $metadata->method[Compensable::class] ?? null;
    }
    
}
