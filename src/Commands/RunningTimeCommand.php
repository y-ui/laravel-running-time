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
    protected $signature = 'running-time {--line= : The maximum number of rows to show} {--start= : Log start date} {--end= : Log end date} {--path= : Statistical path runtime} {--lessMemory : Change file reading mode, Significantly reduce memory usage and increase time spent}';

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

        $this->logPath = config('runningtime.path', storage_path('logs/runningtime/'));
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

        register_shutdown_function(__NAMESPACE__ . '\RunningTimeCommand::errorHandle');

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
        $line = $this->getLogFiles();

        $times = $max = $min = 0;
        $sortedPath = array_pad([], $this->line, 0);

        $count = 0;
        foreach ($line as $logs) {
            !is_array($logs) && $logs = [$logs];
            foreach ($logs as $log) {
                list($time, $p, $params) = explode('||', rtrim($log));
                if ($p == $path) {
                    // 为了避免大数组排序，在遍历中直接排出{$this->line}个请求
                    end($sortedPath);
                    $lastKey = key($sortedPath);
                    if ($time > $sortedPath[$lastKey]) {
                        if (isset($sortedPath[$params]) && $time > $sortedPath[$params]) {
                            $sortedPath[$params] = $time;
                        } else if (!isset($sortedPath[$params])) {
                            unset($sortedPath[$lastKey]);
                            $sortedPath[$params] = $time;
                        }
                        arsort($sortedPath);
                    }

                    $times += $time;

                    if ($time > $max || $max == 0) $max = $time;
                    if ($time < $min || $min == 0) $min = $time;
                    ++$count;
                }
            }

        }

        $sortedPath = array_filter($sortedPath);
        foreach ($sortedPath as $key => &$value) {
            $value = [$value, $key];
        }

        $average = round($times / $count, 2);

        $this->table(['path', 'average', 'max', 'min', 'count'], [[$path, $average, $max, $min, $count]]);

        $this->info("Top {$this->line} request reversed by time and uniqued by params with path $path:");

        $this->table(['time', 'params'], $sortedPath);
    }

    /**
     * 计算耗时最长的path
     */
    public function longestTime()
    {
        $line = $this->getLogFiles();

        $pathTimes = $times = [];

        foreach ($line as $logs) {
            !is_array($logs) && $logs = [$logs];
            foreach ($logs as $log) {
                list($time, $path) = explode('||', rtrim($log));

                if (!isset($pathTimes[$path])) {
                    $pathTimes[$path] = [
                        'path' => $path,
                        'max' => 0,
                        'min' => PHP_INT_MAX,
                        'count' => 0,
                        'total' => 0,
                    ];
                }

                if ($time > $pathTimes[$path]['max']) $pathTimes[$path]['max'] = $time;
                if ($time < $pathTimes[$path]['min']) $pathTimes[$path]['min'] = $time;
                ++$pathTimes[$path]['count'];
                $pathTimes[$path]['total'] += $time;
            }
        }

        foreach ($pathTimes as $path => &$time) {
            $average = round($time['total'] / $time['count'], 2);

            $time = [
                'path' => $path,
                'average' => $average,
                'max' => $time['max'],
                'min' => $time['min'],
                'count' => $time['count'],
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

        foreach ($files as $file) {
            if (file_exists($file)) {
                if ($this->option('lessMemory')) {
                    $fp = fopen($file, 'r');
                    while (($line = fgets($fp)) !== false) {
                        yield $line;
                    }
                    fclose($fp);
                } else {
                    yield file($file);
                }
            }
        }
    }

    /**
     *
     */
    protected function showMemory()
    {
        $this->info('this time memory usage: ' . round(memory_get_usage()/1024/1024, 2) . 'M');
    }

    public static function errorHandle()
    {
        $error = error_get_last();
        if ($error && stripos($error['message'], 'Allowed memory size of') !== false) {
            echo "\n Warnning: Out of memory! you can run command with --lessMemory to reduce memory usage\n";
            exit;
        }
    }
}
