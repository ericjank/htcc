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

    // public function __construct()
    // {
    // }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $result = self::guessBelongsToRelation();

        // 当调用rpc接口时检测是否需要进行事务处理
        if ( $started = $this->recorder->isStarted()) 
        {
            $transaction_id = $this->recorder->getTransactionID();
            $transactions = $this->recorder->getTransactions();

            if (isset($transactions[$transaction_id][$result['class']])) 
            {
                // TODO 如未设置confirm和cancel方法 则默认以调用方法开头以Confirm或Cancel作为方法后缀自动调用
                $transaction = $transactions[$transaction_id][$result['class']];
                var_dump("需要执行事务的rpc接口调用", $transaction);

                $arguments = $proceedingJoinPoint->getArguments();
                $method = $arguments[0];
                $params = isset($arguments[1]) ? $arguments[1] : null;
                $transaction['method'] = $method;
                $transaction['params'] = $params;

                // 如不指定try则该接口全部方法都进行事务处理
                if ( ! isset($transaction['try']) || $method == $transaction['try']) {
                    $confirMethod = (isset($transaction['onConfirm']) && ! empty($transaction['onConfirm'])) ? $transaction['onConfirm'] : $method . 'Confirm';
                    $cancelMethod = (isset($transaction['onCancel']) && ! empty($transaction['onCancel'])) ? $transaction['onCancel'] : $method . 'Cancel';

                    var_dump("参数", $arguments);

                    // TODO 在接口方法内如需兼容事务, 则需要判断当前是否在事务中 inRpcTrans() 方法可以用于判断
                    // 如果是在事务中 出现错误要抛出异常, 以便事务发起端能够截获异常进行后续处理
                    try 
                    {
                        $res = $proceedingJoinPoint->process();

                        // 添加到执行成功的队列
                        $transaction['result'] = $res;
                    }
                    catch(RecvException $e) 
                    {
                        // 对于网络波动造成的异常, 有可能请求已经到达对端接口且执行成功, 但仍然要判定事务中断回滚
                        $this->recorder->setError($transaction);

                        // 抛出异常 其他后续接口调用不会再执行, 也无需回滚等操作
                        // throw new RpcTransactionException($e->getMessage(), $e->getCode());
                        throw new RpcTransactionException($e->getMessage(), $e->getCode());
                    }
                    catch (RpcTransactionException $e) {
                        // 对端接口直接抛出异常
                        $this->recorder->setError($transaction);

                        // 抛出异常 其他后续接口调用不会再执行, 也无需回滚等操作
                        // throw new RpcTransactionException($e->getMessage(), $e->getCode());
                        throw new RpcTransactionException($e->getMessage(), $e->getCode());
                    }
                    catch (RequestException $e) {
                        $this->recorder->setError($transaction);

                        // 抛出异常 其他后续接口调用不会再执行, 也无需回滚等操作
                        // throw new RpcTransactionException($e->getMessage(), $e->getCode());
                        throw new RpcTransactionException($e->getMessage(), $e->getCode());
                    }
                    finally
                    {
                        echo "无论如何都保存这个事务信息用于最后执行回滚或确认\n";

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
