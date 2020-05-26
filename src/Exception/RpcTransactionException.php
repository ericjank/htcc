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
namespace Ericjank\Htcc\Exception;

use Hyperf\RpcClient\Exception\RequestException;
use App\Constants\ErrorCode;
use Hyperf\Server\Exception\ServerException;
use Throwable;

class RpcTransactionException extends ServerException
{
}
