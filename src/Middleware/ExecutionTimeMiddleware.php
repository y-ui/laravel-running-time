<?php

namespace ExecutionTime\Middleware;

use Closure;

class ExecutionTimeMiddleware
{
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
                'uri' => $request->url(),
                'params' => $request->all(),
            ];

            $logJson = json_encode($log, JSON_UNESCAPED_UNICODE);
            $logFile = date("Y-m-d") . '.log';

            file_put_contents(storage_path('logs/ExecutionTime/' . $logFile), $logJson . "\n", FILE_APPEND);
        }

        return $response;
    }
}
