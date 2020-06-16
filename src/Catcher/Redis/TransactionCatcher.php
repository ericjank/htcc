<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Catcher\Redis;

use Hyperf\Utils\ApplicationContext;
use Hyperf\Redis\Redis;
use Ericjank\Htcc\Exception\RpcTransactionException;
use Hyperf\Redis\Lua\Hash\HIncrByFloatIfExists;
use Ericjank\Htcc\Catcher\Redis\Lua\HCheckStatus;
use Ericjank\Htcc\Catcher\Code as CatcherCode;

/**
 * TransactionCatcher 捕捉器(防悬挂、空回滚等)
 * TODO 使用LUA合并, 改造hset用法, 目前存储json串用法不适合hashtable
 */
class TransactionCatcher
{
    /**
     * @var HIncrByFloatIfExists
     */
    private $redisHIncr;

    /**
     * @var HCheckStatus
     */
    private $redisCheckStatus;

    /**
     * @var Redis
     */
    private $redis;

    protected $hash = 'htcc:catcher';

    private $message = '';
    private $code = 0;

    /**
     * @var \Ericjank\Htcc\Recorder
     */
    public $recorder;

    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->redis = $container->get(Redis::class);
        $this->redisHIncr = new HIncrByFloatIfExists($container);
        $this->redisCheckStatus = new HCheckStatus($container);
    }

    public function setKey($hashKey)
    {
        $this->hash = 'htcc:ch:' . $hashKey;
        
        echo "set hash key for catcher: $hashKey\n";
    }

    public function setParams(array $params)
    {
        if ( isset($params['status']))
        {
            throw new RpcTransactionException(sprintf("Htcc error: can not set param %s!", 'status'), 4004);
        }

        if ( ! $this->redis->hMset($this->hash, $params))
        {
            throw new RpcTransactionException(sprintf("Htcc error: can not save param!"), 4004);
        }
    }

    public function getParam(string $key)
    {
        return $this->redis->hGet($this->hash, $key);
    }

    public function setStatus(int $status)
    {
        if ( false === $this->redis->hSet($this->hash, 'status', $status ? 1 : 0))
        {
            throw new RpcTransactionException(sprintf("Htcc error: can not hold hash %s!", $this->hash), 4004);
        }
    }

    public function pass()
    {
        $this->setStatus(0);
    }

    public function try(bool $throw = false): bool
    {
        defer(function() {
            if (! hasRpcTransError() )
            {
                $this->pass();
            }
        });
        
        $status = $this->redisCheckStatus->eval([$this->hash]);

        if ($status !== false) 
        {
            if ($status == 0) // 直接响应保证幂等
            {
                $this->message = '其他请求已经抢先到达, 并且处理完成try阶段任务, 本次无需任何处理, 可直接返回实现幂等';
                $this->code = CatcherCode::HTCC_CATCHER_IDEMPOTENT;

                if ($throw)
                {
                    throw new RpcTransactionException($this->message, $this->code);
                }
                return false;
            }

            else if ($status == 1) // 出现空回滚或悬挂，抛弃本次响应
            {
                // 在lua中已经删除此事务的状态信息
                $this->message = '空回滚或悬挂';
                $this->code = CatcherCode::HTCC_CATCHER_ERR;

                if ($throw)
                {
                    throw new RpcTransactionException($this->message, $this->code);
                }
                return false;
            }
        }

        return true;
    }

    public function confirm($onConfirm = null, bool $throw = false)
    {
        $status = $this->redis->hGet($this->hash, 'status');

        if (false === $status)
        {
            $this->message = '其他请求已经抢先到达, 并且处理完成confirm阶段任务, 本次无需任何处理, 可直接返回实现幂等';
            $this->code = CatcherCode::HTCC_CATCHER_IDEMPOTENT;

            if ($throw)
            {
                throw new RpcTransactionException($this->message, $this->code);
            }

            return false;
        }

        else if (0 != $status)
        {
            $this->message = '发生不可预知错误';
            $this->code = CatcherCode::HTCC_CATCHER_ERR;

            if ($throw)
            {
                throw new RpcTransactionException($this->message, $this->code);
            }
            return false;
        }

        if ($onConfirm && is_callable($onConfirm))
        {
            $result = $onConfirm();

            if ($result)
            {
                $this->release();
            }
            else 
            {
                $this->message = 'confirm回调发生错误';
                $this->code = CatcherCode::HTCC_CATCHER_CALLBACK_ERR;

                if ($throw)
                {
                    throw new RpcTransactionException($this->message, $this->code);
                }
            }

            return $result;
        }

        return true;
    }

    public function cancel($onCancel = null, bool $throw = false)
    {
        $status = $this->redis->hGet($this->hash, 'status');

        if ($status === false) {
            $this->setStatus(1);
            $this->redis->expireAt($this->hash, time() + 3600 * 24 * 7); // 异常状态下 $this->hash 可能永远不会被释放

            $this->message = '其他请求已经抢先到达, 并且处理完成cancel阶段任务, 本次无需任何处理, 可直接返回实现幂等';
            $this->code = CatcherCode::HTCC_CATCHER_IDEMPOTENT;

            if ($throw)
            {
                throw new RpcTransactionException($this->message, $this->code);
            }

            return false;
        }
        else if (0 == $status)
        {
            if ($onCancel && is_callable($onCancel))
            {
                $result = $onCancel();

                if ($result)
                {
                    $this->release();
                }
                else 
                {
                    $this->message = 'cancel回调发生错误';
                    $this->code = CatcherCode::HTCC_CATCHER_CALLBACK_ERR;

                    if ($throw)
                    {
                        throw new RpcTransactionException($this->message, $this->code);
                    }
                }

                return $result;
            }

            return true;
        }

        $this->message = '发生不可预知错误';
        $this->code = CatcherCode::HTCC_CATCHER_ERR;

        if ($throw)
        {
            throw new RpcTransactionException($this->message, $this->code);
        }
        return false;
    }

    public function release()
    {
        if ( ! $this->redis->del($this->hash))
        {
            // TODO LOG
        }
    }

    public function lock($number)
    {
        if (is_callable($number))
        {
            $number = $number();
        }
        
        if ( empty($this->redisHIncr->eval([ $this->hash, 'locked', (float)$number ])) )
        {
             throw new RpcTransactionException(sprintf("Htcc error: can not lock %s for transaction!", $number), 4004);
        }
    }

    public function getLock()
    {
        return $this->redis->hGet($this->hash, 'locked');
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getMessage()
    {
        return $this->message;
    }
}
