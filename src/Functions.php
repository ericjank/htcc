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
use Ericjank\Htcc\Recorder;

if (! function_exists('inRpcTrans')) 
{
    function inRpcTrans() {
        return ApplicationContext::getContainer()->get(Recorder::class)->isStarted() ? true : false;
    }
}

if (! function_exists('hasRpcTransError'))
{
    // TODO 被调用的接口try方法内需调用 hasRpcTransError 防悬挂
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