<?php


return [

    /*
     |---------------------------------------------------------------------------
     | Write Mode
     |---------------------------------------------------------------------------
     |
     | The mode of write file, default write file immediately
     | If the qps of your app is very large, please select delay mode
     |
     | options: delay
     */
    'mode' => 'real_time',

    /*
     |---------------------------------------------------------------------------
     | Delayed Write
     |---------------------------------------------------------------------------
     |
     | When mode select daley
     | If the next request is more than maximum_time seconds from the first request or the cumulative number of logs exceeds maximum_log,
     | all logs in this period are written in batches.
     | And this need redis
     */
    'delay' => [
        'time' => 60, // This is not strictly written after time seconds
        'log' => 60,
    ],

    'path' => storage_path('logs/runningtime/'),

];