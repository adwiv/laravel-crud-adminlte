<?php

namespace Adwiv\Laravel\CrudGenerator;

use Adwiv\Laravel\CrudGenerator\Commands\ControllerMakeCommand;
use Adwiv\Laravel\CrudGenerator\Commands\EnumMakeCommand;
use Adwiv\Laravel\CrudGenerator\Commands\ModelMakeCommand;
use Adwiv\Laravel\CrudGenerator\Commands\RequestMakeCommand;
use Adwiv\Laravel\CrudGenerator\Commands\ResourceMakeCommand;
use Adwiv\Laravel\CrudGenerator\Commands\ViewMakeCommand;
use Illuminate\Support\ServiceProvider;

class CrudGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register() {}

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
                RequestMakeCommand::class,
                ResourceMakeCommand::class,
                ModelMakeCommand::class,
                CrudGenerator::class,
                ViewMakeCommand::class,
                EnumMakeCommand::class,
            ]);
        }
    }
}
