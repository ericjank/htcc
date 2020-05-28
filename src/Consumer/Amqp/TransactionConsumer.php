<?php

declare(strict_types=1);

namespace Ericjank\Htcc\Consumer\Amqp;

use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;
use Hyperf\Amqp\Message\ConsumerMessage;
use Ericjank\Htcc\Recorder;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Logger\LoggerFactory;

class TransactionConsumer extends ConsumerMessage
{
    public $max_retry_limit = 3;

    /**
     * @Inject
     * @var ContainerInterface
     */
    public $container;

    /**
     * @Inject
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var Recorder
     */
    public $recorder;

    public $counter = [];
    public $tid = '';
    public $stepts = [];

    public function __construct(ContainerInterface $container)
    {
        $this->max_retry_limit = config('htcc.max_retry_limit', 3);
        $this->outlogger       = $container->get(LoggerFactory::class)->get('HTCC');
        $this->container       = $container;
    }

    public function getParallels()
    {
        $parallel = [];

        foreach ($this->stepts as $step) 
        {
            $service = $step['service'];
            if ( ! isset($this->counter[$service]))
            {
                $this->counter[$service] = [
                    'retry_' . $this->action . '_count' => 0, // 次数
                    'retry_success' => 0, // 成功
                    'retry_fail' => 0 // 最终失败
                ];
            }

            if ($this->counter[$service]['retry_success'] || $this->counter[$service]['retry_fail'])
            {
                continue;
            }

            if ((++$this->counter[$service]['retry_' . $this->action . '_count']) >= $this->max_retry_limit)
            {
                $this->logger->error(sprintf($this->retrymessage, $service, $step['on' . ucfirst($this->action)], $this->counter[$service]['retry_' . $this->action . '_count'], $this->tid));

                // Retry fail on next time
                $this->counter[$service]['retry_fail'] = 1;
            }

            $parallel []= function() use ($step, $service)
            {
                $container = $this->container->get($service);
                $result = call_user_func_array([$container, $step['on' . ucfirst($this->action)]], $step['params']);

                return [
                    'service' => $service,
                    'method' => $step['on' . ucfirst($this->action)],
                    'result' => $result
                ];
            };
        }

        return $parallel;
    }

    protected function isLastRetry(): bool
    {
        foreach ($this->counter as $retry) 
        {
            if ($retry['retry_fail'] || $retry['retry_success'] || $retry['retry_' . $this->action . '_count'] >= $this->max_retry_limit)
            {
                continue;
            }

            return false;
        }

        return true;
    }

    protected function onLastRetry()
    {
        $this->outlogger->error(sprintf($this->lastmessage, $this->max_retry_limit, $this->tid));
        switch ($this->action) {
            case 'confirm':
                $method = 'confirmFail';
                break;
            case 'cancel':
            default:
                $method = 'rollbackFail';
                break;
        }
        
        $this->recorder->$method($this->tid, $this->counter);
    }

    public function isEnable(): bool
    {
        return config('htcc.producer_driver', 'amqp') != 'amqp' ? false : parent::isEnable();
    }
}