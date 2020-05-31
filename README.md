# component-require

```
composer require ericjank/htcc
 php bin/hyperf.php vendor:publish ericjank/htcc
```

# 注册异常处理器

```
config/autoload/exceptions.php
```

在rpc接口服务配置下新增 Ericjank\Htcc\Exception\Handler\TransactionException::class

例如：
```
return [
    'handler' => [
        'http' => [
            App\Exception\Handler\AppClientExceptionHandler::class,
            App\Exception\Handler\AppValidationExceptionHandler::class,
            App\Exception\Handler\AppTokenValidExceptionHandler::class,
            App\Exception\Handler\UnauthorizedExceptionHandler::class,
            App\Exception\Handler\AppExceptionHandler::class,
        ],
        'jsonrpc' => [
            Ericjank\Htcc\Exception\Handler\TransactionException::class
        ]
    ],
];
```

# 在需要进行事务处理的第一个方法体添加注解

```
use Ericjank\Htcc\Annotation\Compensable;

/**
 * @Compensable(
 *     onConfirm="sendSmsConfirm",
 *     onCancel="sendSmsCancel",
 *     clients={
 *         {
 *             "service": App\JsonRpc\Communal\SmsInterface::class,
 *             "try": "send",
 *             "onConfirm": "sendConfirm",
 *             "onCancel": "sendCancel",
 *         }
 *     }
 * )
 */
public function sendSms($message, $user_id) 
{ 
    ...
}

public function sendSmsConfirm($message, $user_id)
{
    ...
}

public function sendSmsCancel($message, $user_id)
{
    ...
}
```

* try try阶段执行的方法名, 如不指定try则该接口全部方法在本次事务中如有调用都进行事务处理
* onConfirm confirm阶段执行的方法名
* onCancel cancel阶段执行的方法名
* clients 当前事务需要监控的接口, 不是过程中所有的接口都需要事务处理, 方法中使用的不需要事务处理的接口不必配置到监控列表里
* service 接口消费者类

如未设置confirm和cancel方法 则默认以调用方法开头以Confirm或Cancel作为方法后缀自动调用

# 实现try, confirm,cancel方法

被调用端接口需实现try, confirm,cancel方法， 方法内如发生错误需要抛出 Ericjank\Htcc\Exception\RpcTransactionException 异常 , 才能被上层接口捕获并进行相应的处理
为了不影响接口自身在非事务情况下的使用(或者说兼容事务与非事务两种情况)，可以使用 inRpcTrans() 方法判断当前是否在rpc事务中，仅在事务中抛出  RpcTransactionException 异常，其他情况则按照原有流程正常执行即可;

也可以用函数 rpcTransCallback 进行简化处理
```
rpcTransCallback(function() {
    // 非事务状态下执行的代码
}, '事务状态下的异常消息', '事务状态下的异常CODE')
```

# 使用 Ericjank\Htcc\Catcher 处理分布式事务防悬挂、空回滚等问题

## try 阶段

通过注入 Ericjank\Htcc\Catcher 获取对象 $this->catcher

```
// 分布式事务安全验证开始
$tried = $this->catcher->try();

if ( ! $tried)
{
    $code = $this->catcher->getCode();
    switch ($code) {
        case CatcherCode::HTCC_CATCHER_IDEMPOTENT:
            return ['code' => 200];

        case CatcherCode::HTCC_CATCHER_ERR:
            throw new RpcTransactionException($code, $this->catcher->getMessage());
        
        default:
            throw new RpcTransactionException($code, '未知错误');
    }
}
// 分布式事务安全验证结束

// ... 业务代码

// 使用 setParams 方法可以在各个try、confirm、cancel方法之间传递数据
$this->catcher->setParams([
    'u' => 1
]);

// 使用 getParam 方法获取 setParams 设置的数据
$this->catcher->getParam('u');


$this->catcher->lock($value); // 可以记录资源变动数值, 可选, 通常变动的值会通过参数传递到confirm、cancel阶段所以一般无需记录

// 处理成功时设置try阶段完成
$this->catcher->pass();
```

## confirm 阶段
```
// 分布式事务安全验证开始
$confirm = $this->catcher->confirm(function() {
    // 未发现空回滚、防悬挂等问题后执行的回调函数
    // 回调结束后会自动释放锁
    // 如果不设置这个回调, 则在返回成功信息前需要手动释放锁 $this->catcher->release()
    
    // ... 业务代码
});

if ( ! $confirm)
{
    $code = $this->catcher->getCode();
    switch ($code) {
        case CatcherCode::HTCC_CATCHER_IDEMPOTENT:
            return true;

        case CatcherCode::HTCC_CATCHER_ERR:
        case CatcherCode::HTCC_CATCHER_CALLBACK_ERR:
            throw new RpcTransactionException($code, $this->catcher->getMessage());
        
        default:
            throw new RpcTransactionException($code, '未知错误');
    }
}
// 分布式事务安全验证结束

$value = $this->catcher->getLock(); // 获取由lock记录的资源变动数值
```

## cancel 阶段
```
// 分布式事务安全验证开始
$cancel = $this->catcher->cancel(function() {
    // 未发现空回滚、防悬挂等问题后执行的回调函数
    // 回调结束后会自动释放锁
    // 如果不设置这个回调, 则在返回成功信息前需要手动释放锁 $this->catcher->release()

    // ... 业务代码
});

if ( ! $cancel)
{
    $code = $this->catcher->getCode();
    switch ($code) {
        case CatcherCode::HTCC_CATCHER_IDEMPOTENT:
            return true;

        case CatcherCode::HTCC_CATCHER_ERR:
        case CatcherCode::HTCC_CATCHER_CALLBACK_ERR:
            throw new RpcTransactionException($code, $this->catcher->getMessage());
        
        default:
            throw new RpcTransactionException($code, '未知错误');
    }
}
// 分布式事务安全验证结束
```


# 函数

* getRpcTransID 获取当前事务ID(支持在事务发起端和远程接口)
* inRpcTrans 检测是否在事务中
* rpcTransCallback 
* hasRpcTransError 获取事务抛出的错误信息(支持在事务发起端和远程接口的try方法内)
* getRpcTransSteps 获取事务流程数据(支持在事务发起端)
