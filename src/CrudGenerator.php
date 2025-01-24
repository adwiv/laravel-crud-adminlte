<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;

class CrudGenerator extends GeneratorCommand
{
    use CrudHelper {
        CrudHelper::handle as protected handleCrudHelper;
    }

    protected $type = 'CRUD';
    protected $name = 'crud:all';
    protected $description = 'Generate all CRUD files for a model';

    protected function getStub(): string
    {
        throw new \Exception('Stub not implemented');
    }

    public function handle()
    {
        $model = $this->argument('model');

        $modelFullName = $this->qualifyModel($model);
        $modelBaseName = class_basename($modelFullName);
        $this->info("Generating CRUD for {$modelFullName}");

        $table = $this->getCrudTable($modelFullName);

        // If the model has parent models, we need to know the type of the resource
        $resourceType = $this->getCrudControllerType($modelFullName);

        // Check if the model has a parent model
        $parentBaseName = $parentFullName = null;
        if ($resourceType !== 'regular') {
            $parentFullName = $this->getCrudParentModel($modelFullName);
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

        // Generate Enums for the model
        $enumColumns = ColumnInfo::getEnumColumns($table);
        foreach ($enumColumns as $column) {
            $field = $column->name;
            $values = $column->values;
            $this->info('');
            $this->info("Found enum column `$field` in `$table` table with values: " . implode(', ', $values));
            $enumExists = class_exists(Str::studly(Str::singular($field)));

            if (confirm("Do you want to generate an enum for `$field` column?", $enumExists)) {
                $enumName = $this->confirmEnumName(null, $field);
                $this->addEnum($table, $field, $enumName);
                $this->call('crud:enum', ['name' => $enumName, '--table' => $table, '--column' => $field, '--quiet' => true]);
            }
        }

        // Generate the model
        $this->call('crud:model', ['name' => $modelFullName, '--table' => $table, '--quiet' => true]);
        if (!class_exists($modelFullName, true)) $this->fail("Class {$modelFullName} does not exist.");
        if ((new $modelFullName)->getTable() !== $table) $this->fail("Class `{$modelFullName}` does not use `{$table}` table.");

        // Generate Resource
        $resourceFullClass = $this->qualifyClassForType("{$modelBaseName}Resource", 'Resource');
        $this->call('crud:resource', ['name' => $resourceFullClass, '--model' => $modelFullName]);
        if (!class_exists($resourceFullClass, true)) $this->fail("Class {$resourceFullClass} does not exist.");

        // Generate Request
        $requestFullClass = $this->qualifyClassForType("{$modelBaseName}Request", 'Request');
        $args = ['name' => $requestFullClass, '--model' => $modelFullName, '--quiet' => true];
        if (!$parentFullName) $args['--no-parent'] = true;
        if ($parentFullName) $args['--parent'] = $parentFullName;
        $this->call('crud:request', $args);
        if (!class_exists($requestFullClass, true)) $this->fail("Class {$requestFullClass} does not exist.");

        // Generate API Controller
        if ($this->option('api')) {
            $controllerClass = $this->qualifyClassForType("Api/{$modelBaseName}Controller", 'Controller');

            $args = ['name' => $controllerClass, '--model' => $modelFullName, '--routeprefix' => $routePrefix, '--api' => true, '--quiet' => true];
            if ($parentFullName) $args['--parent'] = $parentFullName;
            $args["--$resourceType"] = true;
            $this->call('crud:controller', $args);
            if (!class_exists($controllerClass, true)) $this->fail("Class {$controllerClass} does not exist.");

            exit();
        }

        // Generate Web Controller
        $controllerClass = $this->qualifyClassForType("{$modelBaseName}Controller", 'Controller');
        $args = ['name' => $controllerClass, '--model' => $modelFullName, '--routeprefix' => $routePrefix, '--viewprefix' => $viewPrefix, '--quiet' => true];
        if ($parentFullName) $args['--parent'] = $parentFullName;
        $args["--$resourceType"] = true;
        $this->call('crud:controller', $args);
        if (!class_exists($controllerClass, true)) $this->fail("Class {$controllerClass} does not exist.");

        // Generate Views
        $options = ['--model' => $modelFullName, '--viewprefix' => $viewPrefix, '--routeprefix' => $routePrefix, "--$resourceType" => true, '--quiet' => true];
        if ($parentFullName) $options['--parent'] = $parentFullName;

        $this->call('crud:view', array_merge($options, ['name' => "$viewPrefix.index"]));
        $this->call('crud:view', array_merge($options, ['name' => "$viewPrefix.edit"]));
        $this->call('crud:view', array_merge($options, ['name' => "$viewPrefix.show"]));
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['model', InputArgument::REQUIRED, 'Specify the model class.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['api', null, InputOption::VALUE_NONE, 'Generate controller for api.'],
            ['table', 't', InputOption::VALUE_REQUIRED, 'Use specified table name instead of guessing.'],
            ['parent', 'p', InputOption::VALUE_REQUIRED, 'Use the specified parent class.'],
            ['regular', null, InputOption::VALUE_NONE, 'Generate a regular controller.'],
            ['shallow', null, InputOption::VALUE_NONE, 'Generate a shallow resource controller.'],
            ['nested', null, InputOption::VALUE_NONE, 'Generate a nested resource controller.'],
            ['prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for views and routes.'],
            ['viewprefix', null, InputOption::VALUE_REQUIRED, 'Prefix for the views used.'],
            ['routeprefix', null, InputOption::VALUE_REQUIRED, 'Prefix for the routes used.'],
        ];
    }
}
