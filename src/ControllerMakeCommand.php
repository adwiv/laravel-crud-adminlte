<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;

class ControllerMakeCommand extends GeneratorCommand
{
    use ClassHelper;

    protected $name = 'crud:controller';
    protected $description = 'Create a new controller class';
    protected $type = 'Controller';

    protected function getStub(): string
    {
        $stub = '/stubs/controller/resource.stub';

        if ($this->option('parent')) {
            $stub = '/stubs/controller/nested.stub';
            if ($this->option('shallow')) {
                $stub = '/stubs/controller/shallow.stub';
            }
        }

        if ($this->option('api')) {
            $stub = str_replace('.stub', '.api.stub', $stub);
        }

        return $this->resolveStubPath($stub);
    }

    protected function buildClass($name)
    {
        $model = $this->guessModelName($name);
        $replace = [];

        if ($parent = $this->option('parent')) {
            $replace = $this->buildParentReplacements($parent);
        }

        $replace = $this->buildModelReplacements($replace, $model);

        $viewPrefix = $this->option('view-prefix') ?? $this->option('prefix') ?? '';
        $routePrefix = $this->option('route-prefix') ?? $this->option('prefix') ?? '';

        $replace['{{ viewPrefix }}'] = $this->prefixWithDot($viewPrefix);
        $replace['{{ routePrefix }}'] = $this->prefixWithDot($routePrefix);

        $controllerNamespace = $this->getNamespace($name);
        $replace["use {$controllerNamespace}\Controller;\n"] = '';

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    /**
     * Build the replacements for a parent controller.
     */
    protected function buildParentReplacements($parent): array
    {
        $parentModelClass = $this->fullModelClass($parent);

        if (!class_exists($parentModelClass)) {
            $this->error("{$parentModelClass} model does not exist.");
            die();
        }

        return [
            '{{ namespacedParentModel }}' => $parentModelClass,
            '{{ parentModel }}' => class_basename($parentModelClass),
            '{{ parentModelVariable }}' => lcfirst(class_basename($parentModelClass)),
            '{{ pluralParentModelVariable }}' => Str::plural(lcfirst(class_basename($parentModelClass))),
        ];
    }

    /**
     * Build the model replacement values.
     */
    protected function buildModelReplacements(array $replace, string $model): array
    {
        $modelClass = $this->fullModelClass($model);

        if (!class_exists($modelClass)) {
            if ($this->confirm("A {$modelClass} model does not exist. Do you want to generate it?", true)) {
                $this->call('make:model', ['name' => $modelClass]);
            }
        }

        return array_merge($replace, [
            '{{ namespacedModel }}' => $modelClass,
            '{{ model }}' => class_basename($modelClass),
            '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
            '{{ pluralModelVariable }}' => Str::plural(lcfirst(class_basename($modelClass))),
        ]);
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['api', null, InputOption::VALUE_NONE, 'Generate controller for api.'],
            ['force', null, InputOption::VALUE_NONE, 'Overwrite if file exists.'],
            ['model', null, InputOption::VALUE_REQUIRED, 'Use the specified model class.'],
            ['parent', null, InputOption::VALUE_REQUIRED, 'Use the specified parent class.'],
            ['shallow', null, InputOption::VALUE_NONE, 'Generate a shallow resource controller. Requires --parent option'],
            ['prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for views and routes.'],
            ['view-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for the views used.'],
            ['route-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for the routes used.'],
        ];
    }
}
