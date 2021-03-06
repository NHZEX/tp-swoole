<?php

return [
    'server'       => [
        'listen'    => null, // 监听(存在则优先)
        'host'      => '0.0.0.0', // 监听地址
        'port'      => 9501, // 监听端口
        'mode'      => SWOOLE_PROCESS, // 运行模式 默认为SWOOLE_PROCESS
        'sock_type' => SWOOLE_TCP, // sock type 默认为SWOOLE_SOCK_TCP
        'options'   => [
            'daemonize'                => false,
            'dispatch_mode'            => 2, //固定模式
            'worker_num'               => 1,
            'task_worker_num'          => 4,
            // 运行时文件
            'pid_file'                 => runtime_path() . 'swoole.pid',
            'log_file'                 => runtime_path() . 'swoole.log',
            // 启用Http响应压缩
            'http_compression'         => true,
            // 启用静态文件处理
            'enable_static_handler'    => true,
            // 设置静态文件根目录
            'document_root'            => root_path() . 'public',
            // 设置静态处理器的路径
            'static_handler_locations' => ['/static', '/upload', '/favicon.ico', '/robots.txt'],

            //心跳检测：每60秒遍历所有连接，强制关闭10分钟内没有向服务器发送任何数据的连接
            'heartbeat_check_interval' => 60,
            'heartbeat_idle_time'      => 600,

            'package_max_length' => 20 * 1024 * 1024, // 设置最大数据包尺寸
            'buffer_output_size' => 10 * 1024 * 1024, // 发送输出缓存区内存尺寸
            'socket_buffer_size' => 128 * 1024 * 1024, // 客户端连接的缓存区长度

            'max_request'      => 0,
            'task_max_request' => 0,
            'reload_async'     => true, // 异步安全重启
            'send_yield'       => true, // 发送数据协程调度
        ],
    ],
    'websocket'    => [
        'enabled'       => false,
        'handler'       => '',
        // 'parser' => Parser::class,
        // 'route_file' => base_path() . 'websocket.php',
        'ping_interval' => 25000,
        'ping_timeout'  => 60000,
    ],
    'hot_reload'   => [
        'enable'  => false,
        'name'    => ['*.php'],
        'notName' => [],
        'include' => [app_path()],
        'exclude' => [],
    ],
    // 协程控制
    'coroutine'    => [
        'enable' => true,
        'flags'  => SWOOLE_HOOK_ALL,
    ],
    // 预加载实例（服务启动前执行）
    'concretes'    => [],
    // 重置器 (创建容器时执行)
    'resetters'    => [],
    // 清除实例 (创建容器时执行)
    'instances'    => [],
    // 自定义插件
    'plugins'      => [],
    // 自定义进程类
    'process'      => [],
    // 自定义任务类
    'tasks'        => [],
    // 事件定义类
    'events'       => [],
    // 上下文（容器）管理
    'container'    => [
        // 上下文销毁时要执行的操作
        'destroy' => [],
        // 共享实例 (允许容器间共享的实例，必须服务启动前创建的实例，可搭配预加载使用)
        'shared'  => [],
    ],
    // 监控监测实现
    'health'       => null,
    // 运行内存限制
    'memory_limit' => '512M',
    // 追踪器 (调试)
    'tracker'      => true,
    // 日志记录
    'log'          => [
        'console' => true,
        'channel' => [
            // 日志保存目录
            'path'      => env('LOG_FILE_PATH', '') ?: runtime_path('log'),
            // 日志文件名
            'filename'  => 'server.log',
            // 最大日志文件数量
            'max_files' => 7,
        ],
    ],
    // 连接池
    'pool'         => [
        'db'    => [
            'enable'        => true,
            'max_active'    => 5,
            'max_wait_time' => 5,
        ],
        'cache' => [
            'enable'        => true,
            'max_active'    => 8,
            'max_wait_time' => 5,
        ],
    ],
];
