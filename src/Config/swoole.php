<?php

use think\facade\App;

return [
    'hot_reload' => false,
    'enable_coroutine' => false,
    'resetters' => [],
    'penetrates' => [],
    'process' => [],
    'tasks' => [],
    'events' => [],
    'pools' => [],
    'container' => [
        'destroy' => [],
    ],
    'health' => null,
    'memory_limit' => '512M',
    'resolveLogger' => null,
    'server' => [
        'listen' => null, // 监听(存在则优先)
        'host' => '0.0.0.0', // 监听地址
        'port' => 9501, // 监听端口
        'mode' => SWOOLE_PROCESS, // 运行模式 默认为SWOOLE_PROCESS
        'sock_type' => SWOOLE_TCP, // sock type 默认为SWOOLE_SOCK_TCP
        'options' => [
            'daemonize' => false,
            'dispatch_mode' => 2, //固定模式
            'worker_num' => 1,
            'enable_coroutine' => false,

            'task_worker_num' => 4,
            'task_enable_coroutine' => false,

            'pid_file' => App::getRuntimePath() . 'swoole.pid',
            'log_file' => App::getRuntimePath() . 'swoole.log',

            // 启用Http响应压缩
            'http_compression' => true,
            // 启用静态文件处理
            'enable_static_handler' => true,
            // 设置静态文件根目录
            'document_root' => App::getRootPath() . 'public',
            // 设置静态处理器的路径
            'static_handler_locations' => ['/static', '/upload', '/favicon.ico', '/robots.txt'],

            //心跳检测：每60秒遍历所有连接，强制关闭10分钟内没有向服务器发送任何数据的连接
            'heartbeat_check_interval' => 60,
            'heartbeat_idle_time' => 600,

            'package_max_length' => 20 * 1024 * 1024, // 设置最大数据包尺寸
            'buffer_output_size' => 10 * 1024 * 1024, // 发送输出缓存区内存尺寸
            'socket_buffer_size' => 128 * 1024 * 1024, // 客户端连接的缓存区长度

            'max_request' => 0,
            'task_max_request' => 0,
            'reload_async' => true, // 异步安全重启
            'send_yield' => true, // 发送数据协程调度
        ],
    ],
    'websocket' => [
        'enabled' => false,
        // 'host' => '0.0.0.0', // 监听地址
        // 'port' => 9502, // 监听端口
        // 'sock_type' => SWOOLE_TCP, // sock type 默认为SWOOLE_SOCK_TCP
        'handler' => '',
        // 'parser' => Parser::class,
        // 'route_file' => base_path() . 'websocket.php',
        'ping_interval' => 25000,
        'ping_timeout' => 60000,
    ],
];
