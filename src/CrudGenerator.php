<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

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
        $force = $this->option('force');
        $model = $this->argument('model');

        $modelFullName = $this->qualifyModel($model);
        $modelBaseName = class_basename($modelFullName);
        $this->info("Generating CRUD for {$modelFullName}");

        $table = $this->getCrudTable($modelFullName, false);

        // If the model has parent models, we need to know the type of the resource
        $resourceType = $this->getCrudControllerType($table);

        // Check if the model has a parent model
        $parentBaseName = $parentFullName = null;
        if ($resourceType !== 'regular') {
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

        // Generate the model
        $this->call('crud:model', ['name' => $modelFullName, '--table' => $table, '--quiet' => true, '--force' => $force]);
        if (!class_exists($modelFullName)) $this->fail("Class {$modelFullName} does not exist.");
        if ((new $modelFullName)->getTable() !== $table) $this->fail("Class `{$modelFullName}` does not use `{$table}` table.");

        // Generate Request
        $requestBaseName = text(label: 'Request name:', placeholder: 'E.g. UserRequest', default: "{$modelBaseName}Request");
        $requestFullClass = $this->qualifyClassForType($requestBaseName, 'Request');
        $args = ['name' => $requestFullClass, '--model' => $modelFullName, '--quiet' => true, '--force' => $force, "--$resourceType" => true];
        if ($parentFullName) $args['--parent'] = $parentFullName;
        $this->call('crud:request', $args);
        if (!class_exists($requestFullClass)) $this->fail("Class {$requestFullClass} does not exist.");

        // Generate API Classes
        if ($this->option('api')) {
            // Generate Resource
            $resourceBaseName = text(label: 'Resource name:', placeholder: 'E.g. UserResource', default: "{$modelBaseName}Resource");
            $resourceFullClass = $this->qualifyClassForType($resourceBaseName, 'Resource');
            $this->call('crud:resource', ['name' => $resourceFullClass, '--model' => $modelFullName, '--force' => $force]);
            if (!class_exists($resourceFullClass)) $this->fail("Class {$resourceFullClass} does not exist.");

            // Generate API Controller
            $controllerBaseName = text(label: 'API Controller name:', placeholder: 'E.g. Api/UserController', default: "Api/{$modelBaseName}Controller");
            $controllerClass = $this->qualifyClassForType($controllerBaseName, 'Controller');
            $args = ['name' => $controllerClass, '--model' => $modelFullName, '--routeprefix' => $routePrefix, '--api' => true, '--quiet' => true, '--force' => $force];
            $args['--request'] = $requestFullClass;
            $args['--resource'] = $resourceFullClass;
            if ($parentFullName) $args['--parent'] = $parentFullName;
            $args["--$resourceType"] = true;
            $this->call('crud:controller', $args);
            if (!class_exists($controllerClass)) $this->fail("Class {$controllerClass} does not exist.");

            exit();
        }

        // Generate Web Controller
        $controllerBaseName = text(label: 'Web Controller name:', placeholder: 'E.g. UserController', default: "{$modelBaseName}Controller");
        $controllerClass = $this->qualifyClassForType($controllerBaseName, 'Controller');
        $args = ['name' => $controllerClass, '--model' => $modelFullName, '--routeprefix' => $routePrefix, '--viewprefix' => $viewPrefix, '--quiet' => true, '--force' => $force];
        $args['--request'] = $requestFullClass;
        if ($parentFullName) $args['--parent'] = $parentFullName;
        $args["--$resourceType"] = true;
        $this->call('crud:controller', $args);
        if (!class_exists($controllerClass)) $this->fail("Class {$controllerClass} does not exist.");

        // Generate Views
        $options = ['--model' => $modelFullName, '--viewprefix' => $viewPrefix, '--routeprefix' => $routePrefix, "--$resourceType" => true, '--quiet' => true, '--force' => $force];
        if ($parentFullName) $options['--parent'] = $parentFullName;
        $options['--homeroute'] = $this->getHomeRoute();

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
            ['force', 'f', InputOption::VALUE_NONE, 'Force generation of files even if they already exist.'],
            ['api', null, InputOption::VALUE_NONE, 'Generate controller for api.'],
            ['table', 't', InputOption::VALUE_REQUIRED, 'Use specified table name instead of guessing.'],
            ['parent', 'p', InputOption::VALUE_REQUIRED, 'Use the specified parent class.'],
            ['regular', null, InputOption::VALUE_NONE, 'Generate a regular controller.'],
            ['shallow', null, InputOption::VALUE_NONE, 'Generate a shallow resource controller.'],
            ['nested', null, InputOption::VALUE_NONE, 'Generate a nested resource controller.'],
            ['prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for views and routes.'],
            ['viewprefix', null, InputOption::VALUE_REQUIRED, 'Prefix for the views used.'],
            ['routeprefix', null, InputOption::VALUE_REQUIRED, 'Prefix for the routes used.'],
            ['homeroute', null, InputOption::VALUE_REQUIRED, 'Route name for the home page.'],
        ];
    }
}
