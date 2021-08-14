<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

abstract class ViewBaseMakeCommand extends GeneratorCommand
{
    use ClassHelper;

    protected $viewType = 'undefined';

    /**
     * Parse the class name and format according to the root namespace.
     */
    protected final function qualifyClass($name): string
    {
        return $name;
    }

    /**
     * Get the destination class path.
     */
    protected final function getPath($name): string
    {
        $viewPrefix = $this->option('view-prefix') ?? $this->option('prefix') ?? '';
        return $this->fullViewPath($name, $viewPrefix, $this->viewType);
    }

    protected final function buildClass($name)
    {
        if (!($model = $this->option('model'))) {
            $model = Str::studly(class_basename(str_replace('.', '/', $name)));
        }

        $this->copyBladeScripts();

        $modelClass = $this->fullModelClass($model);
        $ignore = ['id', 'uid', 'uuid', 'remember_token', 'created_at', 'updated_at', 'deleted_at'];
        $fields = $this->getVisibleFields($modelClass, $ignore);

        $replace = array_merge(
            [
                '{{ namespacedModel }}' => $modelClass,
                '{{ model }}' => class_basename($modelClass),
                '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
                '{{ pluralModel }}' => Str::plural(class_basename($modelClass)),
                '{{ pluralModelVariable }}' => Str::plural(lcfirst(class_basename($modelClass))),
                '{{ routePrefix }}' => $this->prefixWithDot($this->option('route-prefix') ?? $this->option('prefix') ?? ''),
            ],
            $this->buildViewReplacements($modelClass, $fields)
        );

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    /**
     * Copy the script stubs to view directory
     */
    protected function copyBladeScripts()
    {
        $dir = $this->laravel->resourcePath('views/layouts/');
        if (!file_exists($dir)) mkdir($dir);
        $files = ["adminlte.blade.php"];
        foreach ($files as $file) {
            if (!file_exists("$dir$file"))
                copy($this->resolveStubPath("/stubs/views/layouts/$file"), "$dir$file");
        }
    }

    protected abstract function buildViewReplacements($modelClass, $fields): array;

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite if file exists.'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Specify Model to use.'],
            ['prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for views and routes.'],
            ['view-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for the views used.'],
            ['route-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for the routes used.'],
        ];
    }
}
