<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Recorder\Redis;

use Hyperf\Utils\ApplicationContext;
use Hyperf\Redis\Redis;

/**
 * TransactionRecorder 事务记录Redis驱动
 * TODO 使用LUA合并, 改造hset用法, 目前存储json串用法不适合hashtable
 */
class TransactionRecorder
{
    use Traits\Rollback;
    use Traits\Confirm;

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
            'create_time' => $now,
            'last_update_time' => $now,
        ];

        $this->redis->hSet("Htcc", $tid, json_encode($data));
        return $tid;
    }

    public function get($tid)
    {
        $data = $this->redis->hget("Htcc", $tid);
        return json_decode($data, true);
    }

    private function set($tid, $params)
    {
        $data = $this->get($tid);
        foreach ($params as $key => $value) {
            $data[$key] = $value;
        }

        return $this->redis->hSet('Htcc', $tid, json_encode($data));
    }

    public function setCounter($tid, $counter)
    {
        return $this->set($tid, [
            'counter' => $counter
        ]);
    }

    public function getCounter($tid)
    {
        $data = $this->get($tid);
        return isset($data['counter']) ? $data['counter'] : [];
    }

    public function setStatus($tid, $status, $steps = null) 
    {
        $data = $this->redis->hget("Htcc", $tid);
        $data = json_decode($data, true);
        $data['status'] = $status;
        $data['last_update_time'] = time();

        if (! is_null($steps) )
        {
            $data['steps'] = $steps;
        }

        return $this->redis->hSet('Htcc', $tid, json_encode($data));
    }

    private function reSetTo($tid, $counter, $status, $to)
    {
        $this->setCounter($tid, $counter);

        $transaction = $this->get($tid);

        $this->redis->hDel('Htcc', $tid);

        $transaction['status'] = $status;

        // TODO 最终持久化到数据库
        return $this->redis->hSet($to, $tid, json_encode($transaction));
    }

    public function confirm($tid, $steps) 
    {
        return $this->setStatus($tid, 'confirm', $steps);
    }

    public function cancel($tid, $steps)
    {
        return $this->setStatus($tid, 'cancel', $steps);
    }
}