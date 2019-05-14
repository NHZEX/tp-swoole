<?php

return [
    'server' => [
        'host' => '0.0.0.0', // 监听地址
        'port' => 9502, // 监听端口
        'mode' => SWOOLE_PROCESS, // 运行模式 默认为SWOOLE_PROCESS
        'sock_type' => SWOOLE_TCP, // sock type 默认为SWOOLE_SOCK_TCP
        'options' => [
            'pid_file' => runtime_path() . 'swoole.pid',
            'log_file' => runtime_path() . 'swoole.log',
            'daemonize' => false,
            // Normally this value should be 1~4 times larger according to your cpu cores.
            'reactor_num' => swoole_cpu_num(),
            'worker_num' => swoole_cpu_num(),
            'task_worker_num' => swoole_cpu_num(),
            'enable_static_handler' => true,
            'document_root' => root_path('public'),
            'package_max_length' => 20 * 1024 * 1024,
            'buffer_output_size' => 10 * 1024 * 1024,
            'socket_buffer_size' => 128 * 1024 * 1024,
            'max_request' => 3000,
            'send_yield' => true,
        ],
    ],
    'websocket' => [
        'host' => '0.0.0.0', // 监听地址
        'port' => 9502, // 监听端口
        'sock_type' => SWOOLE_TCP, // sock type 默认为SWOOLE_SOCK_TCP
        'enabled' => false,
        'handler' => Handler::class,
        'parser' => Parser::class,
        'route_file' => base_path() . 'websocket.php',
        'ping_interval' => 25000,
        'ping_timeout' => 60000,
    ],
    'auto_reload' => false,
    'enable_coroutine' => true,
    'resetters' => [],
    'tables' => [],
];