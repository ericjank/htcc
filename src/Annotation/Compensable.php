<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Ericjank\Htcc\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 * @property $timeout
 */
class Compensable extends AbstractAnnotation
{
    /**
     * @var string
     */
    public $onConfirm;

    /**
     * @var string
     */
    public $onCancel;

    /**
     * 需要进行事务管理的接口表
     * @var array
     */
    public $clients;


    public function __construct($value = null)
    {

        parent::__construct($value);

    }

}
