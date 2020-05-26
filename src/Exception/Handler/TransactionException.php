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
namespace Ericjank\Htcc\Exception\Handler;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Ericjank\Htcc\Exception\RpcTransactionException;
use Hyperf\Rpc\Context as RpcContext;

class TransactionException extends ExceptionHandler
{
     /**
     * @var FormatterInterface
     */
    protected $formatter;

    /**
     * @var RpcContext
     */
    protected $rpcContext;

    public function __construct(FormatterInterface $formatter, RpcContext $rpcContext)
    {
        // $this->logger = $logger;
        $this->formatter = $formatter;
        $this->rpcContext = $rpcContext;
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        // $this->logger->warning($this->formatter->format($throwable));
        if ($throwable instanceof RpcTransactionException) {
            $data = [
                'code' => $throwable->getCode(),
                'message' => $throwable->getMessage(),
            ];

            $this->rpcContext->set('_rpcclienterror', $data);

            // 阻止异常冒泡
            $this->stopPropagation();
            return $response;
        }

        return $response;
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
