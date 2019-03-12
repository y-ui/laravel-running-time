<?php

namespace RunningTime;

use Illuminate\Support\ServiceProvider;
use RunningTime\Command\RunningTimeCommand;

class RunningTimeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {

        if (!$this->app->routesAreCached()) {
            require __DIR__.'/route.php';
        }

        $this->publishes([
            __DIR__.'/config.php' => config_path('runningtime.php'),
        ]);

        $this->registerCommand();
    }

    public function registerCommand()
    {
        $commands = [
            RunningTimeCommand::class,
        ];

        $this->commands($commands);
    }
}
