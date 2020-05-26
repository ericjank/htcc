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

# 被调用端接口需实现try, confirm,cancel方法， 方法内如发生错误需要抛出异常 throw new RpcTransactionException 才能被上层接口捕获并进行相应的处理
为了不影响自身接口在非事务情况下的使用，可以使用 inRpcTrans() 方法判断当前是否在rpc事务中，仅在事务中抛出 throw new RpcTransactionException，其他情况则按照原有流程正常执行即可

# 在需要进行事务处理的第一个方法体添加注解

```
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

* try try阶段执行的方法名
* onConfirm confirm阶段执行的方法名
* onCancel cancel阶段执行的方法名
* clients 当前事务需要监控的接口
* service 接口消费者类
