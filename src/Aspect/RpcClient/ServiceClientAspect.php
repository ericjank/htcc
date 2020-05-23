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

namespace Ericjank\Htcc\Aspect\RpcClient;


// use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\RpcClient\Exception\RequestException;
use Hyperf\RpcClient\ServiceClient;
// use Hyperf\Di\Container;
// use LoyaltyLu\TccTransaction\NsqProducer;
// use LoyaltyLu\TccTransaction\State;
// use LoyaltyLu\TccTransaction\TccTransaction;
use Ericjank\Tcc\Transaction;

/**
 * @Aspect()
 * Class ServiceClientAspect
 * @package Hyperf\TccTransaction\Aspect
 */
class ServiceClientAspect extends AbstractAspect
{


    public $classes = [
        ServiceClient::class . "::__call",
    ];

    /**
     * @I nject()
     * @var State
     */
    // protected $state;

    /**
     * @var Transaction
     */
    private $transaction;

    public function __construct()
    {
        $this->transaction = make(Transaction::class);
    }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $result = self::guessBelongsToRelation();

        print_r($result);

        // $servers = CompensableAnnotationAspect::get($result['class']);
        // if ($servers && count($servers->slave) > 0) {
        //     $tcc_method = array_search($result['function'], $servers->master);
        //     if ($tcc_method == 'tryMethod') {
        //         $params = $proceedingJoinPoint->getArguments()[1][0];
        //         $tid = $this->state->initStatus($servers, $params);
        //         NsqProducer::sendQueue($tid,$proceedingJoinPoint,'tcc-transaction');
        //         return $this->tccTransaction->send($proceedingJoinPoint, $servers, $tcc_method, $tid, $params);
        //     }
        // }
        return $proceedingJoinPoint->process();

    }


    protected function guessBelongsToRelation()
    {
        [$one, $two, $three, $four, $five, $six, $seven, $eight, $nine] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 9);
        return $eight;
    }

}
