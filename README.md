# component-require

```
composer require ericjank/htcc
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
