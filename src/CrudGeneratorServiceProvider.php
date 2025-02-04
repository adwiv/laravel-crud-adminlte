<?php

namespace Adwiv\Laravel\CrudGenerator;

use Adwiv\Laravel\CrudGenerator\Console\ControllerMakeCommand;
use Adwiv\Laravel\CrudGenerator\Console\EnumMakeCommand;
use Adwiv\Laravel\CrudGenerator\Console\ModelMakeCommand;
use Adwiv\Laravel\CrudGenerator\Console\RequestMakeCommand;
use Adwiv\Laravel\CrudGenerator\Console\ResourceMakeCommand;
use Adwiv\Laravel\CrudGenerator\Console\ViewMakeCommand;
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
