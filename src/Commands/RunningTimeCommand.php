<?php


namespace RunningTime\Command;

use Illuminate\Console\Command;


class RunningTimeCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'running-time {--line= : The maximum number of rows to show} {--start= : Log start date} {--end= : Log end date} {--path= : Statistical path runtime}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Statistics request run time';

    public $line;

    /** @var \DateTime $start */
    public $start;

    /** @var \DateTime $end */
    public $end;

    protected $logPath;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->logPath = storage_path('logs/runningtime');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set('memory_limit', config('runningtime.memory_limit', '128M'));
        $st = microtime(true);

        $options = $this->options();

        $this->line = $options['line'] ?? 10;
        try {
            $this->start = isset($options['start']) ? (new \DateTime($options['start'])) : (new \DateTime())->modify('-6 days');
            $this->end = isset($options['end']) ? (new \DateTime($options['end'])) : (new \DateTime());
        } catch (\Exception $exception) {
            preg_match('/__construct\(\): (.*?) at/', $exception->getMessage(), $match);
            $this->error($match[1] ?? 'Invalid date format');
            return;
        }

        if (isset($options['path'])) {
            $this->pathTime($options['path']);
        } else {
            $this->longestTime();
        }

        $this->info('time cost: ' . round(microtime(true) - $st, 2) . ' seconds');
        $this->info('max memory usage: ' . round(memory_get_peak_usage(true)/1024/1024, 2) . 'M');
    }

    /**
     * 统计某一个path的数据
     *
     * @param $path
     */
    public function pathTime($path)
    {
        $pathTimes = $this->getLogFiles();

        $times = $max = $min = 0;
        $sortedPath = array_pad([], $this->line, 0);

        foreach ($pathTimes as $key => &$log) {
            $log = explode('||', rtrim($log));
            if ($log[1] == $path) {
                $time = $log[0];

                // 为了避免大数组排序，在遍历中直接排出{$this->line}个请求
                end($sortedPath);
                $lastKey = key($sortedPath);
                if ($time > $sortedPath[$lastKey]) {
                    if (isset($sortedPath[$log[2]]) && $time > $sortedPath[$log[2]]) {
                        $sortedPath[$log[2]] = $time;
                    } else if (!isset($sortedPath[$log[2]])) {
                        unset($sortedPath[$lastKey]);
                        $sortedPath[$log[2]] = $time;
                    }
                    arsort($sortedPath);
                }

                $log = [
                    'time' => $time,
                    'params' => $log['2'],
                ];
                $times += $time;

                if ($time > $max || $max == 0) $max = $time;
                if ($time < $min || $min == 0) $min = $time;
            } else {
                unset($pathTimes[$key]);
            }
        }

        foreach ($sortedPath as $key => &$value) {
            if (empty($value)) {
                unset($sortedPath[$key]);
                continue;
            }
            $value = [$value, $key];
        }

        $average = round($times / count($pathTimes), 2);
        $count = count($pathTimes);

        $this->table(['path', 'average', 'max', 'min', 'count'], [[$path, $average, $max, $min, $count]]);

        $this->info("Top {$this->line} request reversed by time and uniqued by params with path $path:");

        $this->table(['time', 'params'], $sortedPath);
    }

    /**
     * 计算耗时最长的path
     */
    public function longestTime()
    {
        $logs = $this->getLogFiles();

        $pathTimes = $times = [];

        foreach ($logs as $log) {
            $log = explode('||', rtrim($log));
            $pathTimes[$log[1]][] = $log[0];
        }

        foreach ($pathTimes as $path => &$time) {
            $cnt = count($time);
            $max = max($time);
            $min = min($time);
            $average = round(array_sum($time) / $cnt, 2);

            $time = [
                'path' => $path,
                'average' => $average,
                'max' => $max,
                'min' => $min,
                'count' => $cnt,
            ];

            $times[$path] = $average;
        }

        arsort($times);
        $times = array_slice($times, 0, $this->line);
        foreach ($times as $key => &$time) {
            $time = $pathTimes[$key];
        }

        $this->table(['path', 'average', 'max', 'min', 'count'], $times);

        if (!empty($times)) {
            $this->info('run this command to view single stats: php artisan running-time --path="'. current($times)['path'] .'"');
        }
    }

    /**
     * 获取所有符合条件的日志内容
     *
     * @return array
     */
    protected function getLogFiles()
    {
        $files = [];
        $days = $this->end->diff($this->start)->format('%a');

        for ($n = 0; $n <= $days; $n++) {
            $files[] = $this->logPath . '/' . $this->start->format('Y-m-d') . '.log';
            $this->start->modify('+1 days');
        }

        $contents = [];

        foreach ($files as $file) {
            if (file_exists($file)) {
                $contents = array_merge($contents, file($file));
            }
        }

        return $contents;
    }

    /**
     *
     */
    protected function showMemory()
    {
        $this->info('this time memory usage: ' . round(memory_get_usage()/1024/1024, 2) . 'M');
    }

}