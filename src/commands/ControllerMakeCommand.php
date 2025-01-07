<?php

namespace Adwiv\Laravel\CrudGenerator\Commands;

use Adwiv\Laravel\CrudGenerator\ClassHelper;
use Adwiv\Laravel\CrudGenerator\CrudHelper;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ControllerMakeCommand extends GeneratorCommand
{
    use CrudHelper;

    protected $name = 'crud:controller';
    protected $description = 'Create a new controller class';
    protected $type = 'Controller';

    private string $resourceType;

    protected function getStub(): string
    {
        $stub = "/stubs/controller/{$this->resourceType}.stub";

        if ($this->option('api')) {
            $stub = str_replace('.stub', '.api.stub', $stub);
        }

        return $this->resolveStubPath($stub);
    }

    protected function buildClass($name)
    {
        // Deduce the model name
        $modelFullName = $this->getCrudModel($name);
        $modelBaseName = class_basename($modelFullName);

        // Get the resource type
        $this->resourceType = $this->getCrudNestedType($modelFullName);

        // Check if the model has a parent model
        $parentBaseName = $parentFullName = null;
        if ($this->resourceType !== 'regular') {
            $parentFullName = $this->getCrudParentModel($modelFullName);
            $parentBaseName = $parentFullName ? class_basename($parentFullName) : null;
        }

        // Get the route prefix
        $routePrefix = $this->getCrudRoutePrefix($modelBaseName, $parentBaseName);
        $routePrefixParts = explode('.', $routePrefix);
        $modelRoutePrefix = array_pop($routePrefixParts);
        $parentRoutePrefix = array_pop($routePrefixParts);

        // Get the view prefix
        $viewPrefix = $this->getCrudViewPrefix($routePrefix);

        $replace = [];

        if ($parentFullName) {
            $replace = $this->buildParentReplacements($parentFullName, $parentBaseName, $parentRoutePrefix);
        }

        $replace = $this->buildModelReplacements($replace, $modelFullName, $modelBaseName, $modelRoutePrefix);

        $replace['{{ viewprefix }}'] = $viewPrefix;
        $replace['{{ routeprefix }}'] = $routePrefix;

        $controllerNamespace = $this->getNamespace($name);
        $replace["use {$controllerNamespace}\Controller;\n"] = '';

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    /**
     * Build the replacements for a parent controller.
     */
    protected function buildParentReplacements($parentFullName, $parentBaseName, $parentRoutePrefix): array
    {
        return [
            '{{ namespacedParentModel }}' => $parentFullName,
            '{{ parentModel }}' => $parentBaseName,
            '{{ parentModelVariable }}' => Str::singular($parentRoutePrefix),
            '{{ pluralParentModelVariable }}' => $parentRoutePrefix,
        ];
    }

    /**
     * Build the model replacement values.
     */
    protected function buildModelReplacements(array $replace, string $modelFullName, string $modelBaseName, string $modelRoutePrefix): array
    {
        return array_merge($replace, [
            '{{ namespacedModel }}' => $modelFullName,
            '{{ model }}' => $modelBaseName,
            '{{ modelVariable }}' => Str::singular($modelRoutePrefix),
            '{{ pluralModelVariable }}' => $modelRoutePrefix,
        ]);
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the controller.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['api', null, InputOption::VALUE_NONE, 'Generate controller for api.'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite if file exists.'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Use the specified model class.'],
            ['parent', null, InputOption::VALUE_REQUIRED, 'Use the specified parent class.'],
            ['shallow', null, InputOption::VALUE_NONE, 'Generate a shallow resource controller.'],
            ['prefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for views and routes.'],
            ['viewprefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for the views used.'],
            ['routeprefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for the routes used.'],
        ];
    }
}
