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

namespace Ericjank\Htcc\Aspect;

use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\RpcClient\ProxyFactory;
use Hyperf\RpcClient\ServiceClient;
use Ericjank\Htcc\Annotation\Compensable;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Traits\Container;

/**
 * @Aspect
 */
class CompensableAnnotationAspect extends AbstractAspect
{
    use Container;

    public $classes = [];

    public $annotations = [
        Compensable::class,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $container = ApplicationContext::getContainer();
        $annotation = $this->getAnnotations($proceedingJoinPoint);

        $annotation->master = [
            'service' => $proceedingJoinPoint->className,
            'tryMethod' => $proceedingJoinPoint->methodName,
            'onConfirm' => $annotation->onConfirm,
            'onCancel' => $annotation->onCancel
        ];

        $annotation->master['proxy'] = $container->get($annotation->master['service']);

        foreach ($annotation->steps as $key => $item) {
            $annotation->steps[$key]['proxy'] = $container->get($item['service']);
        }

        // $container->set($annotation->master['proxy'], $annotation);
        $container->set(get_class($annotation->master['proxy']), $annotation);
        echo "在注解阶段输出\n";
        return $proceedingJoinPoint->process();

    }

    public function getAnnotations(ProceedingJoinPoint $proceedingJoinPoint): Compensable
    {
        $metadata = $proceedingJoinPoint->getAnnotationMetadata();
        return $metadata->method[Compensable::class] ?? null;
    }
}
