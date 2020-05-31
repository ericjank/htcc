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
return [
    'max_retry_limit' => env('TCC_MAX_RETRY_LIMIT', 3), // 最多重试次数
    'max_detection_time'=> env('TCC_MAX_DETECTION_TIME', 5), // 检测补偿事务时间
    'producer_driver' => env('TCC_PRODUCER_DRIVER', 'amqp'),
    'recorder_driver' => env('TCC_RECORDER_DRIVER', 'redis'),
    'catcher_driver' => env('TCC_CATCHER_DRIVER', 'redis'),
    // 'recorder_driver' => env('TCC_RECORDER_DRIVER', 'redis'), // 是否开启防空回滚
    'node_id' => env('TCC_NODE_ID', ''),
];