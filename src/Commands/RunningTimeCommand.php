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
    protected $signature = 'running-time {--line= : 最多展示多少行} {--start= : 开始时间} {--end= : 结束时间}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计页面运行时间';

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
        $options = $this->options();

        $this->line = $options['line'] ?? 10;
        $this->start = $options['start'] ? (new \DateTime($options['start'])) : (new \DateTime())->modify('-6 days');
        $this->end = $options['end'] ? (new \DateTime($options['end'])) : (new \DateTime());

        $this->longestTime();
    }

    /**
     * 计算耗时最长的path
     */
    public function longestTime()
    {
        $logs = $this->getLogFiles();

        $pathTimes = $times = [];

        foreach ($logs as $log) {
            $log = json_decode(trim($log), true);
            $pathTimes[$log['path']][] = $log['time'];
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
}