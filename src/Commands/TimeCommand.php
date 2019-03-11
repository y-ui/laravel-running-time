<?php


namespace ExecutionTime\Command;

use Illuminate\Console\Command;


class TimeCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'execution-time {line=10 : 最多展示多少行} {--startTime? : 开始时间} {--endTime? : 结束时间}';

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

        $this->logPath = storage_path('logs/ExecutionTime');

        $this->line = $this->argument('line');
        $this->start = $this->option('startTime') ?? (new \DateTime())->modify('-7 days');
        $this->end = $this->option('endTime') ?? (new \DateTime());
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $this->longestTime();
    }

    public function longestTime()
    {
        $logs = $this->getLogFiles();

        $urlTimes = $times = [];

        foreach ($logs as $log) {
            $log = json_decode(trim($log), true);
            $urlTimes[$log['url']][] = $log['time'];
        }


        foreach ($urlTimes as $url => &$time) {
            $cnt = count($time);
            $max = max($time);
            $min = min($time);
            $average = round(array_sum($time) / $cnt);

            $time = [
                'url' => $url,
                'average' => $average,
                'max' => $max,
                'min' => $min,
            ];

            $times[$url] = $average;
        }

        rsort($times);
        $times = array_slice($times, 0, $this->line);

        $this->table(['url', 'average', 'max', 'min'], $times);
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

        for ($n = 0; $n < $days; $n++) {
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