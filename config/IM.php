<?php

return [

    /**
     * 监听相关配置
     */
    'listen' => [
        'ip'   => '0.0.0.0',
        'port' => 58582
    ],

    'socket' => [
        'type' => defined('SWOOLE_SOCK_TCP') ? \SWOOLE_SOCK_TCP : 1
    ],

    'enable_coroutine' => false,

    /**
     * 进程相关配置
     */
    'process' => [
        // 进程前缀
        'prefix' => 'IM'
    ],

    'settings' => [
        'daemonize'                => 0,
        'dispatch_mode'            => 2,
        'heartbeat_check_interval' => 10,  // 每10秒侦测一次心跳
        'heartbeat_idle_time'      => 60, // 一个TCP连接如果在60秒内未向服务器端发送数据
        'reactor_num'              => function_exists('\swoole_cpu_num') ? \swoole_cpu_num() * 2 : 4,
        'worker_num'               => function_exists('\swoole_cpu_num') ? \swoole_cpu_num() * 2 : 8,
        'task_worker_num'          => function_exists('\swoole_cpu_num') ? \swoole_cpu_num() * 2 : 8,
        'task_ipc_mode'            => 1,
        'task_max_request'         => 5000,
        'task_tmpdir'              => @is_writable('/dev/shm/') ? '/dev/shm' : '/tmp',
        'max_request'              => 3000,
        'open_tcp_nodelay'         => true,
        'log_file'                 => dirname(__DIR__) . '/storage/logs/IM.log',
        'log_level'                => 4,
        'buffer_output_size'       => 16 * 1024 * 1024,
        'socket_buffer_size'       => 128 * 1024 * 1024,
        'package_max_length'       => 4 * 1024 * 1024,
        'reload_async'             => true,
        'max_wait_time'            => 60,
        'enable_reuse_port'        => true
        /**
         * More settings of Swoole
         *
         * @see https://wiki.swoole.com/wiki/page/274.html  Chinese
         * @see https://www.swoole.co.uk/docs/modules/swoole-server/configuration  English
         */
    ]
];