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
namespace Ericjank\Htcc\Catcher\Redis\Lua;

use Hyperf\Redis\Lua\Script;

class HCheckStatus extends Script
{
    public function getScript(): string
    {
        return <<<'LUA'
    local status = redis.call('hGet', KEYS[1], 'status');

    if status == nil then
        return false;
    elseif status == 1 then
        return 1;
    elseif status == 0 then
        redis.call('hDel', KEYS[1], 'status');
        return 0;
    end
    return true;
LUA;
    }

    /**
     * @param null|bool $data
     * @return null|bool
     */
    public function format($data)
    {
        return (bool)$data;
    }

    protected function getKeyNumber(array $arguments): int
    {
        return 1;
    }
}
