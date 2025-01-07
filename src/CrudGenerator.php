<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CrudGenerator extends Command
{
    use ClassHelper;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'crud:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create complete CRUD code';

    private function classExists($className): bool
    {
        $path = $this->fullClassPath($className);
        return file_exists($path);
    }

    public function handle()
    {
        $model = trim($this->argument('model'));
        $modelClass = $this->fullModelClass($model);
        $modelName = class_basename($modelClass);

        if (!($table = $this->option('table'))) {
            if ($this->classExists($modelClass)) {
                $table = (new $modelClass())->getTable();
            } else {
                $table = Str::snake(Str::plural($model));
            }
        }
        echo "Creating CRUD for '$modelClass' using '$table' table\n";

        // Generate Model
        $classPath = $this->fullClassPath($modelClass);
        if (!file_exists($classPath) || $this->confirm("$modelClass already exists. Do you want to overwrite it?", false)) {
            $this->call('crud:model', ['name' => $modelClass, '--table' => $table, '--force' => true]);
        }

        // Generate Request
        $requestClass = $this->fullRequestClass("{$modelName}Request");
        $classPath = $this->fullClassPath($requestClass);
        if (!file_exists($classPath) || $this->confirm("$requestClass already exists. Do you want to overwrite it?", false)) {
            $this->call('crud:request', ['name' => $requestClass, '--model' => $modelClass, '--force' => true]);
        }

        // Generate Resource
        $resourceClass = $this->fullResourceClass("{$modelName}Resource");
        $classPath = $this->fullClassPath($resourceClass);
        if (!file_exists($classPath) || $this->confirm("$resourceClass already exists. Do you want to overwrite it?", false)) {
            $this->call('crud:resource', ['name' => $resourceClass, '--model' => $modelClass, '--force' => true]);
        }

        $viewPrefix = $this->option('view-prefix') ?? $this->option('prefix') ?? '';
        $routePrefix = $this->option('route-prefix') ?? $this->option('prefix') ?? '';

        // Generate Controller
        $controllerClass = $this->fullControllerClass("{$modelName}Controller");
        $classPath = $this->fullClassPath($controllerClass);
        if (!file_exists($classPath) || $this->confirm("$controllerClass already exists. Do you want to overwrite it?", false)) {
            $this->call('crud:controller', ['name' => $controllerClass, '--model' => $modelClass, '--force' => true, '--view-prefix' => $viewPrefix, '--route-prefix' => $routePrefix]);
        }

        // Generate Index View
        $viewName = strtolower(Str::plural($modelName));
        $viewPath = $this->fullViewPath($viewName, $viewPrefix, 'index');
        if (!file_exists($viewPath) || $this->confirm("$viewName.index view already exists. Do you want to overwrite it?", false)) {
            $this->call('crud:view-index', ['name' => $viewName, '--model' => $modelClass, '--force' => true, '--view-prefix' => $viewPrefix, '--route-prefix' => $routePrefix]);
        }

        // Generate Edit View
        $viewName = strtolower(Str::plural($modelName));
        $viewPath = $this->fullViewPath($viewName, $viewPrefix, 'edit');
        if (!file_exists($viewPath) || $this->confirm("$viewName.edit view already exists. Do you want to overwrite it?", false)) {
            $this->call('crud:view-edit', ['name' => $viewName, '--model' => $modelClass, '--force' => true, '--view-prefix' => $viewPrefix, '--route-prefix' => $routePrefix]);
        }

        // Generate Show View
        $viewName = strtolower(Str::plural($modelName));
        $viewPath = $this->fullViewPath($viewName, $viewPrefix, 'show');
        if (!file_exists($viewPath) || $this->confirm("$viewName.show view already exists. Do you want to overwrite it?", false)) {
            $this->call('crud:view-show', ['name' => $viewName, '--model' => $modelClass, '--force' => true, '--view-prefix' => $viewPrefix, '--route-prefix' => $routePrefix]);
        }
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
            ['table', null, InputOption::VALUE_REQUIRED, 'Use specified table name instead of guessing.'],
            ['prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for views and routes.'],
            ['view-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for the views used.'],
            ['route-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for the routes used.'],
        ];
    }
}
