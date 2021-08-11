<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Support\ServiceProvider;

class CrudGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ControllerMakeCommand::class,
                ModelMakeCommand::class,
                RequestMakeCommand::class,
                ResourceMakeCommand::class,
                CrudGenerator::class,
                ViewIndexMakeCommand::class,
                ViewEditMakeCommand::class,
            ]);
        }
    }
}
