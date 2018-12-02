<?php

namespace IM\Events\Dispatchers;

use IM\Traits\LogTrait;

class SqlListener implements \Illuminate\Contracts\Events\Dispatcher
{
    use LogTrait;

    /**
     * Dispatch an event and call the listeners.
     *
     * @param  string|object $event
     * @param  mixed         $payload
     * @param  bool          $halt
     *
     * @return array|null
     */
    public function dispatch($event, $payload = [], $halt = false) {
        if ($event instanceof \Illuminate\Database\Events\QueryExecuted) {
            $sql = $event->sql;
            if ($event->bindings) {
                foreach ($event->bindings as $v) {
                    $sql = preg_replace('/\\?/', "'" . addslashes($v) . "'", $sql, 1);
                }
            }
            $this->log($sql, 'SQL');
        }
        return null;
    }

    public function listen($events, $listener) {}
    public function hasListeners($eventName) {}
    public function subscribe($subscriber) {}
    public function until($event, $payload = []) {}
    public function push($event, $payload = []) {}
    public function flush($event) {}
    public function forget($event) {}
    public function forgetPushed() {}
}  