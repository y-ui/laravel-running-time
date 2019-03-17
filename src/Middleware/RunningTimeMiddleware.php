<?php

namespace RunningTime\Middleware;

use Closure;

class RunningTimeMiddleware
{
    /**
     * Last time of putting log to cache.
     */
    const REDIS_TIME = 'laravel_running_time:time';

    /**
     * Redis list for caching request's log.
     */
    const REDIS_LIST = 'laravel_running_time:list';

    /**
     * @var bool
     */
    protected $isDelayMode;

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

        if (!app()->runningInConsole()) {
            $log = [
                'time' => round(microtime(true) - LARAVEL_START, 2),
                'path' => $request->path(),
                'params' => json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            ];

            $logText = implode('||', $log);
            $logText .= $this->isDelayMode ? '' : "\n";

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
        $this->checkLogDir();

        if ($this->isDelayMode) {
            app('redis')->rpush(self::REDIS_LIST, $data);

            if ($this->readyForWriting()) {
                $data = $this->pullCachedLogs();
            }
        }

        $this->append($data);
    }

    /**
     * Read history log from cache and then delete from cache.
     *
     * @return string
     */
    private function pullCachedLogs()
    {
        $len = app('redis')->llen(self::REDIS_LIST);
        $logs = app('redis')->lrange(self::REDIS_LIST, 0, $len - 1);

        app('redis')->ltrim(self::REDIS_LIST, $len, -1);

        return implode('', $logs);
    }

    /**
     * Determine if logs is ready for writing in delay mode.
     *
     * @return bool
     */
    private function readyForWriting()
    {
        return app('redis')->llen(self::REDIS_LIST) >= config('runningtime.delay.log')
            || time() - app('redis')->get(self::REDIS_TIME) >= config('runningtime.delay.time');
    }

    /**
     * Append log data to file.
     *
     * @param string $data
     */
    private function append($data)
    {
        $logFilePath = config('runningtime.path') . '/' . date('Y-m-d') . '.log';

        file_put_contents($logFilePath, $data, FILE_APPEND);

        if ($this->isDelayMode) {
            app('redis')->set(self::REDIS_TIME, time());
        }
    }

    /**
     * Ensure the path of logs exists.
     *
     * @return void
     */
    private function checkLogDir()
    {
        $path = config('runningtime.path');
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}
