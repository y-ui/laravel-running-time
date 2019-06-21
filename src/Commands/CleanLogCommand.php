<?php



namespace RunningTime\Command;

use Illuminate\Console\Command;


class CleanLogCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'running-time:clear {--all : Delete all logs} {--recent= : Keep logs for the last ? days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up log files';

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
        $options = $this->options();

        if (isset($options['all'])) {
            $this->deleteAll();
        } elseif (isset($options['recent'])) {
            $this->deleteRecent();
        } else {
            $this->info('use --all delete all log files, use --recent=30 keep logs for the last 30 days');
        }
    }

    /**
     * 删除日志文件保留最近的 xx 天
     */
    protected function deleteRecent()
    {
        $days = abs($this->option('recent')) + 1;
        $start = (new \DateTime())->modify("- $days days");

        $keepFiles = [];
        for ($n = 0; $n <= $days; $n++) {
            $fileName = $this->logPath . '/' . $start->format('Y-m-d') . '.log';
            $keepFiles[$fileName] = 1;
            $start->modify('+1 days');
        }

        $allFiles = $this->getFiles();
        $cnt = 0;
        foreach ($allFiles as $file) {
            if (is_file($file) && !isset($keepFiles[$file])) {
                unlink($file);
                $this->line($file . ' deleted');
                $cnt++;
            }
        }
        $this->info("$cnt log files has been deleted");
    }

    /**
     * 删除所有日志文件
     */
    protected function deleteAll()
    {
        $files = $this->getFiles();

        $cnt = 0;

        foreach ($files as $file) {
            if (is_file($file) && preg_match('/.*?\d{4}-\d{2}-\d{2}\.log$/', $file, $match)) {
                unlink($file);
                $cnt++;
            }
        }

        $this->info("All log files has been cleared, $cnt files deleted");
    }

    /**
     * 获取目录下的所有文件
     *
     * @return array
     */
    protected function getFiles()
    {
        return array_map(function ($val) {return implode([$this->logPath, '/', $val]);}, array_diff(scandir($this->logPath), ['..', '.']));
    }


}