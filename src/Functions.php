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
use Hyperf\Utils\ApplicationContext;
use Hyperf\Rpc\Context as RpcContext;

if (! function_exists('inRpcTrans')) 
{
    function inRpcTrans() {
        $started = ApplicationContext::getContainer()->get(RpcContext::class)->get('_rpctransaction_started');
        return $started ? true : false;
    }
}

if (! function_exists('getRpcTransID')) 
{
    function getRpcTransID() {
        $started = ApplicationContext::getContainer()->get(RpcContext::class)->get('_transaction_id');
        return $started ? true : false;
    }
}

if (! function_exists('hasRpcTransError'))
{
    // TODO 被调用的接口try方法内需调用 hasRpcTransError 防悬挂
    function hasRpcTransError()
    {
        // 上下文? redis?

        $rpc_error = ApplicationContext::getContainer()->get(RpcContext::class)->get('_rpctransaction_error');
        return ! empty($rpc_error) ? true : false;
    }
}

if (! function_exists('getTransSteps'))
{
    function getTransSteps() 
    {
        return ApplicationContext::getContainer()->get(RpcContext::class)->get('_rpctransaction_steps') ?? [];
    }
}