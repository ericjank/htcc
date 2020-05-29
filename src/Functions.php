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
use Ericjank\Htcc\Exception\RpcTransactionException;
use Ericjank\Htcc\Recorder;

if (! function_exists('getRpcTransID')) 
{
    function getRpcTransID() {
        return ApplicationContext::getContainer()->get(Recorder::class)->getTransactionID();
    }
}

if (! function_exists('inRpcTrans')) 
{
    function inRpcTrans() {
        return ApplicationContext::getContainer()->get(Recorder::class)->isStarted() ? true : false;
    }
}

if (! function_exists('rpcTransCallback')) 
{
    function rpcTransCallback($normal, $message = "接口异常", $code = 0) {

        if (inRpcTrans())
        {
            throw new RpcTransactionException($message, $code);    
        }

        return $normal();
    }
}

if (! function_exists('hasRpcTransError'))
{
    function hasRpcTransError()
    {
        $rpc_error = ApplicationContext::getContainer()->get(Recorder::class)->getError();
        return ! empty($rpc_error) ? $rpc_error : false;
    }
}

if (! function_exists('getRpcTransSteps'))
{
    function getRpcTransSteps() 
    {
        return ApplicationContext::getContainer()->get(Recorder::class)->getSteps();
    }
}