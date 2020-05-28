<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Recorder\Redis\Traits;

trait Rollback
{
    public function rollbackSuccess($tid, $counter)
    {
        return $this->reSetTo($tid, $counter, 'rollbacked', 'HtccRollbakSucc');
    }

    public function rollbackFail($tid, $counter) 
    {
        return $this->reSetTo($tid, $counter, 'rollbackfail', 'HtccRollbakFail');
    }
}