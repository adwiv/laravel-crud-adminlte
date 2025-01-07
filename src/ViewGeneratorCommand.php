<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

abstract class ViewGeneratorCommand extends GeneratorCommand
{
    use ClassHelper;

    protected $view = 'view';
    protected $type = 'View';
    protected readonly string $viewType;

    protected abstract function buildViewReplacements($modelClass, $fields): array;

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
        $model = $this->option('model') ?? $name;
        return $this->fullViewPath($name, $viewPrefix, $this->view);
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
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    /**
     * Copy the script stubs to view directory
     */
    protected function copyBladeScripts()
    {
        $dir = $this->laravel->resourcePath('views/components/layouts/');
        if (!file_exists($dir)) mkdir($dir);
        $files = ["crud.blade.php"];
        foreach ($files as $file) {
            if (!file_exists("$dir$file"))
                copy($this->resolveStubPath("/stubs/views/components/layouts/$file"), "$dir$file");
        }
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Specify Model to use.'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the view even if the view already exists'],
            ['prefix', 'p', InputOption::VALUE_REQUIRED, 'Prefix for the generated views and routes.'],
            ['view-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix parent directory for the generated views.'],
            ['route-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for the routes used by the generated views.'],
        ];
    }
}
