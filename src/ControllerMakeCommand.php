<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;

class ControllerMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'crud:controller';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new controller class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Controller';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
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

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param string $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__ . $stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Http\Controllers';
    }

    /**
     * Build the class with the given name.
     *
     * Remove the base controller import if we are already in the base namespace.
     *
     * @param string $name
     * @return string
     */
    protected function buildClass($name)
    {
        if (!($model = $this->option('model'))) {
            $suffix = $this->type;
            $baseLen = strlen($suffix);
            $baseName = class_basename($name);
            if (strlen($baseName) > $baseLen && str_ends_with($baseName, $suffix)) {
                $model = substr($baseName, 0, -$baseLen);
            } else {
                $this->error('Could not guess model name. Please use --model option');
            }
        }

        $replace = [];

        if ($parent = $this->option('parent')) {
            $replace = $this->buildParentReplacements($parent);
        }

        $replace = $this->buildModelReplacements($replace, $model);

        $replace['{{ viewPrefix }}'] = $this->option('view') ?? '';
        $replace['{{ routePrefix }}'] = $this->option('route') ?? '';

        $controllerNamespace = $this->getNamespace($name);
        $replace["use {$controllerNamespace}\Controller;\n"] = '';

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    /**
     * Build the replacements for a parent controller.
     *
     * @return array
     */
    protected function buildParentReplacements($parent)
    {
        $parentModelClass = $this->parseModel($parent);

        if (!class_exists($parentModelClass)) {
            if ($this->confirm("A {$parentModelClass} model does not exist. Do you want to generate it?", true)) {
                $this->call('make:model', ['name' => $parentModelClass]);
            }
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
     *
     * @param array $replace
     * @param string $model
     * @return array
     */
    protected function buildModelReplacements(array $replace, string $model)
    {
        $modelClass = $this->parseModel($model);

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
     * Get the fully-qualified model class name.
     *
     * @param string $model
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function parseModel($model)
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['api', null, InputOption::VALUE_NONE, 'Generate controller for api.'],
            ['force', null, InputOption::VALUE_NONE, 'Overwrite if file exists.'],
            ['model', null, InputOption::VALUE_REQUIRED, 'Use the specified model class.'],
            ['parent', null, InputOption::VALUE_REQUIRED, 'Use the specified parent class.'],
            ['shallow', null, InputOption::VALUE_NONE, 'Generate a shallow resource controller. Requires --parent option'],
            ['route', null, InputOption::VALUE_REQUIRED, 'Prefix for the routes used.'],
            ['view', null, InputOption::VALUE_REQUIRED, 'Prefix for the views used.'],
        ];
    }
}
