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
    protected $signature = 'execution-time {--startTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计页面运行时间';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

    }
}