<?php

namespace RunningTime\Middleware;

use Closure;

class RunningTimeMiddleware
{

    /**
     * @var bool
     */
    protected $isDelayMode;

    protected $redisList;

    protected $redisTime;

    protected $redis;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $this->isDelayMode = config('runningtime.mode') == 'delay';
        $this->redisList = config('app.name') . ':laravel_running_time:list';
        $this->redisTime = config('app.name') . ':laravel_running_time:time';
        $this->isDelayMode && $this->redis = app('redis');

        if (!app()->runningInConsole()) {
            $log = [
                'time' => round(microtime(true) - LARAVEL_START, 2),
                'path' => $request->path(),
                'params' => json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            ];

            $logText = implode('||', $log) . "\n";

            $this->writeRequestLog($logText);
        }

        return $response;
    }

    /**
     * Write request time to log.
     *
     * @param string $data
     */
    private function writeRequestLog($data)
    {
        if ($this->isDelayMode) {
            $this->redis->rpush($this->redisList, $data);

            if ($this->readyForWriting()) {
                $this->pullCachedLogs();
            }
        } else {
            $this->append($data);
        }
    }

    /**
     * Read history log from cache and then delete from cache.
     *
     * @return string
     */
    private function pullCachedLogs()
    {
        $len = $this->redis->llen($this->redisList);
        $this->redis->multi();
        $this->redis->lrange($this->redisList, 0, $len - 1);
        $this->redis->ltrim($this->redisList, $len, -1);
        $this->redis->set($this->redisTime, time());
        $logs = $this->redis->exec();

        $this->append($logs[0] ?? []);
    }

    /**
     * Determine if logs is ready for writing in delay mode.
     *
     * @return bool
     */
    private function readyForWriting()
    {
        return app('redis')->llen($this->redisList) >= config('runningtime.delay.log')
            || time() - app('redis')->get($this->redisTime) >= config('runningtime.delay.time');
    }

    /**
     * Append log data to file.
     *
     * @param string $data
     */
    private function append($data)
    {
        $logFilePath = config('runningtime.path') . '/' . date('Y-m-d') . '.log';
        $this->checkLogDir();

        file_put_contents($logFilePath, $data, FILE_APPEND);

        if ($this->isDelayMode) {

        }
    }

    /**
     * Ensure the path of logs exists.
     *
     * @return void
     */
    private function checkLogDir()
    {
        $path = config('runningtime.path', storage_path('logs/runningtime/'));
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}
