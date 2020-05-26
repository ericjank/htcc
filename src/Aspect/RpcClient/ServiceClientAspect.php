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

namespace Ericjank\Htcc\Aspect\RpcClient;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\RpcClient\Exception\RequestException;
use Hyperf\RpcClient\ServiceClient;
use Hyperf\Rpc\Context as RpcContext;
use Hyperf\Rpc\Exception\RecvException;
use Ericjank\Htcc\Exception\RpcTransactionException;
use Ericjank\Htcc\Recorder;

/**
 * @Aspect()
 * Class ServiceClientAspect
 */
class ServiceClientAspect extends AbstractAspect
{
    public $classes = [
        ServiceClient::class . "::__call",
    ];

    /**
     * @Inject
     * @var RpcContext
     */
    protected $rpcContext;

    /**
     * @Inject
     * @var Recorder
     */
    protected $recorder;

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $result = self::guessBelongsToRelation();

        if ( $started = $this->recorder->isStarted()) 
        {
            $transaction_id = $this->recorder->getTransactionID();
            $transactions = $this->recorder->getTransactions();

            if (isset($transactions[$transaction_id][$result['class']])) 
            {
                $transaction = $transactions[$transaction_id][$result['class']];

                $arguments = $proceedingJoinPoint->getArguments();
                $method = $arguments[0];
                $params = isset($arguments[1]) ? $arguments[1] : null;
                $transaction['method'] = $method;
                $transaction['params'] = $params;

                if ( ! isset($transaction['try']) || $method == $transaction['try']) {
                    $confirMethod = (isset($transaction['onConfirm']) && ! empty($transaction['onConfirm'])) ? $transaction['onConfirm'] : $method . 'Confirm';
                    $cancelMethod = (isset($transaction['onCancel']) && ! empty($transaction['onCancel'])) ? $transaction['onCancel'] : $method . 'Cancel';

                    try 
                    {
                        $res = $proceedingJoinPoint->process();
                        $transaction['result'] = $res;
                    }
                    catch(RecvException $e) 
                    {
                        // 对于网络波动造成的异常, 有可能请求已经到达对端接口且执行成功, 但仍然要判定事务中断回滚
                        $this->recorder->setError($transaction);
                        throw new RpcTransactionException($e->getMessage(), $e->getCode());
                    }
                    catch (RpcTransactionException $e) {
                        $this->recorder->setError($transaction);
                        throw new RpcTransactionException($e->getMessage(), $e->getCode());
                    }
                    catch (RequestException $e) {
                        $this->recorder->setError($transaction);
                        throw new RpcTransactionException($e->getMessage(), $e->getCode());
                    }
                    finally
                    {
                        // 无论如何都保存这个事务信息用于最后执行回滚或确认
                        $this->recorder->addStep($transaction);
                    }

                    return $res;
                }
            }
        }

        return $proceedingJoinPoint->process();
    }

    protected function guessBelongsToRelation()
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 9);
        return isset($backtrace[7]) ? $backtrace[7] : null;
    }

}
