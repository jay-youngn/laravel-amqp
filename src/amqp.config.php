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
