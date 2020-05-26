<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Recorder\Redis;

use Hyperf\Utils\ApplicationContext;
use Hyperf\Redis\Redis;

/**
 * TransactionRecorder 事务记录Redis驱动
 * TODO 使用LUA合并
 */
class TransactionRecorder
{
    /**
     * @var Redis
     */
    private $redis;

    public function __construct()
    {
        $this->redis = ApplicationContext::getContainer()->get(Redis::class);
    }

    public function add($tid, $annotation)
    {
        $now = time();
        $data = [
            'annotation' => $annotation,
            'status' => 'normal',
            'retried_cancel_count' => 0,
            'retried_confirm_count' => 0,
            'retried_cancel_nsq_count' => 0,
            'retried_confirm_nsq_count' => 0,
            'retried_max_count' => config('htcc.max_retry_count', 1),
            'create_time' => $now,
            'last_update_time' => $now,
        ];

        print_r($data);
        $this->redis->hSet("Htcc", $tid, json_encode($data));
        return $tid;
    }

    public function setStatus($tid, $status) 
    {
        $data = $this->redis->hget("Htcc", $tid);
    }

    public function confirm($tid, $steps) 
    {
        $data = $this->redis->hget("Htcc", $tid);

        $data = json_decode($data, true);

        $data['status'] = 'confirm';
        $data['steps'] = $steps;
        $data['last_update_time'] = time();

        return $this->redis->hSet('Htcc', $tid, json_encode($data));
    }

    public function cancel($tid, $steps)
    {
        $data = $this->redis->hget("Htcc", $tid);

        $data = json_decode($data, true);

        $data['status'] = 'cancel';
        $data['steps'] = $steps;
        $data['last_update_time'] = time();

        return $this->redis->hSet('Htcc', $tid, json_encode($data));
    }
}