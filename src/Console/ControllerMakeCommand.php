<?php

namespace Adwiv\Laravel\CrudGenerator\Console;

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

    private bool $isApi = false;
    private string $resourceType;

    protected function getStub(): string
    {
        $stub = "/stubs/controller/{$this->resourceType}.stub";

        if ($this->isApi) {
            $stub = str_replace('.stub', '.api.stub', $stub);
        }

        return $this->resolveStubPath($stub);
    }

    protected function buildClass($name)
    {
        $this->isApi = $this->option('api');

        // Deduce the model name
        $modelFullName = $this->getCrudModel($name);
        $modelBaseName = class_basename($modelFullName);
        $table = (new $modelFullName)->getTable();

        // Get the resource type
        $this->resourceType = $this->getCrudControllerType($table);

        // Check if the model has a parent model
        $parentBaseName = $parentFullName = null;
        if ($this->resourceType !== 'regular') {
            $parentFullName = $this->getCrudParentModel($table);
            $parentBaseName = $parentFullName ? class_basename($parentFullName) : null;
        }

        // Get the route prefix
        $routePrefix = $this->getCrudRoutePrefix($modelBaseName, $parentBaseName);
        $routePrefixParts = explode('.', $routePrefix);
        $modelRoutePrefix = array_pop($routePrefixParts);
        $parentRoutePrefix = array_pop($routePrefixParts);

        // Get the view prefix
        $viewPrefix = '';
        if (!$this->option('api')) $viewPrefix = $this->getCrudViewPrefix($modelBaseName, $parentBaseName, $routePrefix);

        $replace = [];

        if ($parentFullName) {
            $replace = $this->buildParentReplacements($parentFullName, $parentBaseName, $parentRoutePrefix);
        }

        $replace = $this->buildModelReplacements($replace, $modelFullName, $modelBaseName, $modelRoutePrefix);

        $shallowRoutePrefix = $routePrefix;
        if ($this->resourceType === 'shallow') {
            $routePrefixParts = explode('.', $shallowRoutePrefix);
            if (count($routePrefixParts) >= 2) {
                array_splice($routePrefixParts, -2, 1);
                $shallowRoutePrefix = implode('.', $routePrefixParts);
            }
        }

        $replace['{{ viewprefix }}'] = $viewPrefix;
        $replace['{{ routeprefix }}'] = $routePrefix;
        $replace['{{ shallowrouteprefix }}'] = $shallowRoutePrefix;

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
        $requestModel = $this->getCrudRequestClass($modelBaseName);

        if ($this->isApi) {
            $resourceModel = $this->getCrudResourceClass($modelBaseName);
            $replace = array_merge($replace, [
                '{{ resourceModel }}' => class_basename($resourceModel),
                '{{ resourceFullModel }}' => $this->qualifyClassForType($resourceModel, 'Resource'),
            ]);
        }

        return array_merge($replace, [
            '{{ namespacedModel }}' => $modelFullName,
            '{{ model }}' => $modelBaseName,
            '{{ requestModel }}' => class_basename($requestModel),
            '{{ requestFullModel }}' => $this->qualifyClassForType($requestModel, 'Request'),
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
            ['parent', 'p', InputOption::VALUE_REQUIRED, 'Use the specified parent class.'],
            ['regular', null, InputOption::VALUE_NONE, 'Generate a regular controller.'],
            ['shallow', null, InputOption::VALUE_NONE, 'Generate a shallow resource controller.'],
            ['nested', null, InputOption::VALUE_NONE, 'Generate a nested resource controller.'],
            ['request', null, InputOption::VALUE_REQUIRED, 'Use the specified request class.'],
            ['resource', null, InputOption::VALUE_REQUIRED, 'Use the specified resource class.'],
            ['prefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for views and routes.'],
            ['viewprefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for the views used.'],
            ['routeprefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for the routes used.'],
        ];
    }
}
