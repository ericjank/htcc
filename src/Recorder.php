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

namespace Ericjank\Htcc;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Rpc\Context as RpcContext;
use Ericjank\Htcc\Producer as TransactionProducer;

class Recorder
{
    /**
     * @Inject
     * @var RpcContext
     */
    protected $context;

    private $handle = null;

    public function __construct()
    {
        $driver = ucfirst(config('htcc.recorder_driver', 'redis'));

        switch ($driver) {
            case 'Redis':
                $this->handle = new \Ericjank\Htcc\Recorder\Redis\TransactionRecorder();
                break;
            
            default:
                # code...
                break;
        }
    }

    public function startTransaction($params)
    {
        $this->context->set('_rpctransaction_started', true);
        return $this->handle->add($params);
    }

    public function confirm($tid, $steps)
    {
        $this->handle->confirm($tid, $steps);
        return TransactionProducer::confirm($tid);
    }

    public function cancel($tid, $steps)
    {
        $this->handle->cancel($tid, $steps);
        return TransactionProducer::cancel($tid);
    }
}
