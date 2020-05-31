<?php

declare(strict_types = 1);

namespace Ericjank\Htcc\Catcher;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

/**
 * @Constants
 */
class Code extends AbstractConstants
{

    /**
     * @Message("空回滚或悬挂！")
     */
    const HTCC_CATCHER_ERR = 500;

    /**
     * @Message("其他请求已经抢先到达, 并且处理完成try阶段任务, 本次无需任何处理, 可直接返回实现幂等")
     */
    const HTCC_CATCHER_IDEMPOTENT = 501;

    /**
     * @Message("回调发生错误")
     */
    const HTCC_CATCHER_CALLBACK_ERR = 505;

}
