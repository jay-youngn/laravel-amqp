# MQ

## 快速开始

### 0. 添加composer包
```javascript
"require": {
    "ginnerpeace/laravel-amqp": "xxxxxxx"
}
```


### 1. 添加ServiceProvider和Facade

```php
<?php
return [
    // ....

    'providers' => array(
        // ...
        Gimq\Providers\MQServiceProvider::class,
    ),

    'aliases' => array(
        // ...
        'MQ' => Gimq\Facades\MQ::class,
    ),

    // ...
];
```

### 2. 添加配置

```shell
$ php artisan vendor:publish --provider="Gimq\MQServiceProvider"
```

- 执行后将生成`config/amqp.php`文件
- 需按照 connections.rabbitmq.queues 里的结构进行队列配置
- 调用时只使用队列别名
- 特别注意各队列的channel_id必须大于0且不能相同, 如嫌麻烦可以用 $i++ \_(:з」∠)\_

```php
<?php

return [
    // 有多种连接之后可以用dotenv
    'default' => 'rabbitmq',
    'connections' => [
        'rabbitmq' => [
            'host' => env('MQ_HOST'),
            'port' => env('MQ_PORT'),
            'user' => env('MQ_USER'),
            'password' => env('MQ_PASSWORD'),
            'vhost' => env('MQ_VHOST'),
            // 上面是mq基本信息
            // ----------------------------------------
            // 下面配置每个队列, 这里add_coupon为一个示例
            'queues' => [
                'add_coupon' => [
                    'queue' => 'add_coupon',
                    'queue_flags' => [
                        'durable' => TRUE,
                        'routing_key' => 'coupon.*',
                    ],
                    'message_properties' => [
                        // delivery_mode value说明
                        // DELIVERY_MODE_NON_PERSISTENT 1
                        // DELIVERY_MODE_PERSISTENT 2
                        'delivery_mode' => 2,
                        'content_encoding' => 'UTF-8',
                        'priority' => 0,
                        'content_type' => 'application/json',
                    ],
                    // channel_id要大于0, 项目中各队列不可以重复
                    'channel_id' => 1,
                    'exchange_name' => 'topic_coupon',
                    'exchange_type' => 'topic',
                    'exchange_flags' => NULL,
                ],
            ],
        ],
    ],
];

```


### 3. 发送消息

```php
<?php

// 支持下列调用方式
// 1. 指定连接
MQ::connection('rabbitmq')->publish('message', 'add_coupon');

// 2. 常规发送 (将使用配置中的默认连接)
MQ::publish('message', 'add_coupon');

// 3. 批量发送
// 传入数组, 适合常规数据量下的发送
MQ::batchPublish(['message', 'message2'], 'add_coupon');

// 4. 另一种批量发送
// 传入生成器, 适合大批量发送, 不进行变量存储 节省内存
$msgClosure = function () {
    for ($i=0; $i < 1000000; $i++) {
        yield 'message' . $i;
    }
};
MQ::batchPublish($msgClosure(), 'add_coupon');

// 5. 不使用交换机发送
// 传入第三个参数为true后, 定义队列时Exchange会为空, routingKey为队列名称
MQ::publish('message', 'add_coupon', TRUE);

```

### 4. 消费

敬请期待....
