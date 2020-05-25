<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Recorder\Redis;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\Redis;
use Hyperf\Snowflake\IdGeneratorInterface;
use Ericjank\Htcc\Producer as TransactionProducer;

/**
 * TransactionRecorder 事务记录Redis驱动
 * TODO 使用LUA合并
 */
class TransactionRecorder
{
    /**
     * @Inject()
     * @var Redis
     */
    private $redis;

    public function add($params)
    {
        $container = ApplicationContext::getContainer();
        $tid = (string)$container->get(IdGeneratorInterface::class)->generate();

        $now = time();
        $data = [
            'tid' => $tid,
            'content' => $params,
            'status' => 'normal',
            'retried_cancel_count' => 0,
            'retried_confirm_count' => 0,
            'retried_cancel_nsq_count' => 0,
            'retried_confirm_nsq_count' => 0,
            'retried_max_count' => config('htcc.max_retry_count', 1),
            'create_time' => $now,
            'last_update_time' => $now,
        ];
        $this->redis->hSet("Htcc", $tid, json_encode($data));
        return $tid;
    }

    public function setStatus($tid, $status) 
    {
        $data = $this->redis->hget("Htcc", $tid);
    }

    public function confirm($tid) 
    {
        $data = $this->redis->hget("Htcc", $tid);

        $data = json_decode($data, true);

        $data['status'] = 'confirm';
        $data['last_update_time'] = time();

        return $this->redis->hSet('Htcc', $tid, json_encode($data));
    }

    public function cancel($tid, $steps)
    {
        $data = $this->redis->hget("Htcc", $tid);

        $data = json_decode($data, true);

        $data['steps'] = $steps;
        $data['status'] = 'cancel';
        $data['last_update_time'] = time();

        return $this->redis->hSet('Htcc', $tid, json_encode($data));
    }
}