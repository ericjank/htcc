<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Recorder\Redis\Traits;

trait Confirm
{
    public function confirmSuccess($tid, $counter)
    {
        return $this->reSetTo($tid, $counter, 'confirmed', 'HtccConfirmSucc');
    }

    public function confirmFail($tid, $counter) 
    {
        return $this->reSetTo($tid, $counter, 'confirmfail', 'HtccConfirmFail');
    }
}