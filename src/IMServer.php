<?php

namespace IM;

use IM\Events\Dispatchers\SqlListener;
use IM\Traits\LogTrait;
use IM\Traits\ProcessTitleTrait;
use Illuminate\Database\Capsule\Manager;

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

        try {
            (new \Dotenv\Dotenv($this->app->basePath('')))->load();
        } catch (\Dotenv\Exception\InvalidPathException $e) {
            //
        }

        // 初始化 数据库
        $this->app->singleton(\Illuminate\Support\Facades\DB::class, function () {
            $capsule = new Manager();
            $capsule->addConnection([
                'read'      => [
                    'host'     => env('DB_HOST'),
                    'username' => env('DB_USERNAME'),
                    'password' => env('DB_PASSWORD'),
                    'database' => env('DB_DATABASE')
                ],
                'write'     => [
                    'host'     => env('DB_HOST'),
                    'username' => env('DB_USERNAME'),
                    'password' => env('DB_PASSWORD'),
                    'database' => env('DB_DATABASE')
                ],
                'driver'    => 'mysql',
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => env('DB_PREFIX'),
            ]);
            $capsule->setFetchMode(\PDO::FETCH_ASSOC);
            // 使用设置静态变量方法，令当前的 Capsule 实例全局可用
            // illuminate/database/Connection.php tryAgainIfCausedByLostConnection 方法处理 server has gone away 问题
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
            if (!env('DEBUG')) {
                $capsule->setEventDispatcher(new SqlListener());
            }
            return $capsule;
        });

        // 初始化 缓存数据库
        $this->app->singleton(\Redis::class, function () {
            $redis = new \Redis();
            $redis->connect(env('REDIS_HOST'), env('REDIS_PORT'));
            if ($password = env('REDIS_PASSWORD')) {
                $redis->auth($password);
            }
            return $redis;
        });

        // 初始化 debug 设置
        if (!env('DEBUG')) {
            error_reporting(E_ALL ^ E_DEPRECATED);
            ini_set('error_reporting', E_ALL);
            ini_set('display_errors', 'ON');
        } else {
            error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
        }
    }

    public function onWorkerError(\swoole_http_server $server, $workerId, $workerPId, $exitCode, $signal) {
        $this->log(sprintf('worker[%d] error: exitCode=%s, signal=%s', $workerId, $exitCode, $signal), 'ERROR');
    }

    /**
     * 发送错误信息
     *
     * @param $client_id
     * @param $code
     * @param $msg
     */
    public function sendErrorMessage($client_id, $code, $msg) {
        $this->sendJson($client_id, ['cmd' => 'error', 'code' => $code, 'msg' => $msg]);
    }

    /**
     * 发送JSON数据
     *
     * @param $client_id
     * @param $array
     */
    public function sendJson($client_id, $array) {
        $msg = json_encode($array);
        if ($this->server->push($client_id, $msg) === false) {
            $this->server->close($client_id);
        }
    }

    public function onMessage(\swoole_websocket_server $server, \swoole_websocket_frame $frame) {
        $msg = $frame->data;
        $msg = json_decode($msg, true);
        if (empty($msg['cmd'])) {
            $this->sendErrorMessage($frame->fd, 101, "invalid command");
            return;
        }
        $func = 'cmd' . $msg['cmd'];
        if (method_exists($this, $func)) {
            $this->$func($frame->fd, $msg);
        } else {
            $this->sendErrorMessage($frame->fd, 102, "command $func no support.");
            return;
        }
    }

    function cmdMessage($clientId, $data) {
        $sendData = [
            'code' => 200,
            'msg'  => 'succ',
            'data' => $data['data'],
            'cmd'  => 'Message'
        ];
        foreach ($this->server->connections as $fd) {
            if ($fd != $clientId) {
                $this->sendJson($fd, $sendData);
            }
        }
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