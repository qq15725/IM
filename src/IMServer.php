<?php

namespace IM;

use IM\Traits\LogTrait;
use IM\Traits\ProcessTitleTrait;

class IMServer
{
    use LogTrait;
    use ProcessTitleTrait;

    /**
     * The application instance.
     *
     * @var Application
     */
    public $app;

    /**
     * @var \swoole_websocket_server
     */
    protected $server;

    public function __construct($app) {
        $this->app = $app;
        $this->app->configure('IM');

        $ip         = $this->getConfig('listen.ip');
        $port       = $this->getConfig('listen.port');
        $socketType = $this->getConfig('socket.type');
        if ($socketType === \SWOOLE_SOCK_UNIX_STREAM) {
            $socketDir = dirname($ip);
            if (!file_exists($socketDir)) {
                mkdir($socketDir);
            }
        }

        $settings   = $this->getConfig('settings');
        $useSSL     = isset($settings['ssl_cert_file'], $settings['ssl_key_file']);
        $socketType = $useSSL ? $socketType | \SWOOLE_SSL : $socketType;
        $server     = new \swoole_websocket_server($ip, $port, \SWOOLE_PROCESS, $socketType);
        $server->set($settings);

        $server->on('start', [$this, 'onStart']);
        $server->on('shutdown', [$this, 'onShutdown']);
        $server->on('managerStart', [$this, 'onManagerStart']);
        $server->on('workerStart', [$this, 'onWorkerStart']);
        $server->on('workerError', [$this, 'onWorkerError']);
        $server->on('request', [$this, 'onRequest']);
        $server->on('message', [$this, 'onMessage']);
        $server->on('task', [$this, 'onTask']);
        $server->on('finish', [$this, 'onFinish']);
        $this->server = $server;
    }

    public function getConfig($param = '') {
        return $this->app['config']['IM' . ($param ? '.' . $param : $param)];
    }

    public function onStart(\swoole_http_server $server) {
        foreach (spl_autoload_functions() as $function) {
            spl_autoload_unregister($function);
        }
        $this->setProcessTitle(sprintf('%s:master', $this->getConfig('process.prefix')));
    }

    public function onShutdown(\swoole_http_server $server) {
        $this->log('server shutdownd');
    }

    public function onManagerStart(\swoole_http_server $server) {
        $this->setProcessTitle(sprintf('%s:manager', $this->getConfig('process.prefix')));
    }

    public function onWorkerStart(\swoole_server $server, $workerId) {
        function_exists('opcache_reset') && opcache_reset();
        function_exists('apc_clear_cache') && apc_clear_cache();
        clearstatcache();
        if ($workerId >= $server->setting['worker_num']) {
            $process = 'task_worker';
        } else {
            $process = 'worker';
            if (!empty($this->getConfig('enable_coroutine'))) {
                \Swoole\Runtime::enableCoroutine();
            }
        }
        $this->setProcessTitle(sprintf('%s:%s_%d', $this->getConfig('process.prefix'), $process, $workerId));
        $this->log('workerStart ' . $workerId);

        $this->app->flush();
    }

    public function onWorkerError(\swoole_http_server $server, $workerId, $workerPId, $exitCode, $signal) {
        $this->log(sprintf('worker[%d] error: exitCode=%s, signal=%s', $workerId, $exitCode, $signal), 'ERROR');
    }

    public function onRequest(\swoole_http_request $request, \swoole_http_response $response) {
        $response->header('Content-type', 'text/html; charset=UTF-8');
        $response->end('500 ArgumentCountError');
    }

    public function onMessage(\swoole_websocket_server $server, \swoole_websocket_frame $frame) {
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
        $server->push($frame->fd, "this is server");
    }

    public function onTask(\swoole_server $server, $taskId, $srcWorkerId, $data) {
    }

    public function onFinish(\swoole_server $server, $taskId, $data) {
    }

    public function start() {
        $this->app->boot();
        $this->server->start();
    }
}