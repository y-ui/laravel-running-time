<?php

namespace RunningTime\Middleware;

use Closure;

class RunningTimeMiddleware
{

    const REDIS_TIME = 'laravel_running_time:time';

    const REDIS_LIST = 'laravel_running_time:list';

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response =  $next($request);

        if (!app()->runningInConsole()) {
            $log = [
                'time' => round(microtime(true) - LARAVEL_START, 2),
                'path' => $request->path(),
                'params' => json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            ];

            $logText = implode('||', $log);
            $this->writeData($logText);
        }

        return $response;
    }

    private function writeData($data)
    {
        $logFile = date("Y-m-d") . '.log';

        if (config('runningtime.mode') == 'delay') {
            $this->checkLogDir();
            $redis = app('redis');

            $redis->rpush(self::REDIS_LIST, $data . "\n");

            if ($redis->llen(self::REDIS_LIST) >= config('runningtime.delay.log')
                || time() - $redis->get(self::REDIS_TIME) >= config('runningtime.delay.time')
            ) {
                $len = $redis->llen(self::REDIS_LIST);
                $logs = $redis->lrange(self::REDIS_LIST, 0, $len - 1);
                file_put_contents(config('runningtime.path') . '/' . $logFile, implode('', $logs), FILE_APPEND);
                $redis->ltrim(self::REDIS_LIST, $len, -1);
                $redis->set(self::REDIS_TIME, time());
            }
        } else {
            $this->checkLogDir();
            file_put_contents(config('runningtime.path') . '/' . $logFile, $data . "\n", FILE_APPEND);
        }
    }

    private function checkLogDir()
    {
        $path = config('runningtime.path');
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

}
