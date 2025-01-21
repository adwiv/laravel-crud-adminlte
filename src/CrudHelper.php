<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\text;

trait CrudHelper
{
    protected function baseNamespace(): string
    {
        return trim($this->laravel->getNamespace(), '\\');
    }

    protected function defaultNamespace($rootNamespace, $type = null): string
    {
        $type = $type ?? $this->type;
        if ($type == 'View') return $rootNamespace . '\Views';
        if ($type == 'Model') return $rootNamespace . '\Models';
        if ($type == 'Request') return $rootNamespace . '\Http\Requests';
        if ($type == 'Resource') return $rootNamespace . '\Http\Resources';
        if ($type == 'Controller') return $rootNamespace . '\Http\Controllers';
        $this->fail("Unknown class type '$this->type'.");
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $this->defaultNamespace($rootNamespace);
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath(string $stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__ . $stub;
    }

    protected function getCrudModel(string $name): string
    {
        $model = $this->option('model');
        if (!$model) {
            $model = $this->guessCrudModel($name);
            $model = $this->confirmCrudModel($model ?? '');
        }

        $model = $this->qualifyModel($model);
        if (class_exists($model)) return $model;

        $this->fail("Model class {$model} does not exist.");
    }

    private function guessCrudModel(string $name): ?string
    {
        $suffix = $this->type;
        $baseLen = strlen($suffix);
        $baseName = class_basename($name);
        if (strlen($baseName) > $baseLen && str_ends_with($baseName, $suffix)) {
            return substr($baseName, 0, -$baseLen);
        }
        return null;
    }

    private function confirmCrudModel(string $model): string
    {
        return text(
            label: 'Model class name:',
            placeholder: 'E.g. User',
            default: $model ?? '',
            required: 'Model class name is required.',
            hint: $this->type . ' will be generated for this model.',
            transform: fn(string $value) => $this->qualifyModel($value),
            validate: function (string $value) {
                return class_exists($value) ? null : "Model $value does not exist.";
            }
        );
    }

    protected function getCrudNestedType(string $model): string
    {
        if ($this->option('shallow')) return 'shallow';
        if ($this->option('parent')) return 'nested';

        $parents = $this->guessCrudParentModels($model);
        if (empty($parents)) return 'regular';

        return select(
            label: 'Resource type:',
            options: ['regular' => 'Regular', 'nested' => 'Nested', 'shallow' => 'Shallow'],
            default: 'regular',
        );
    }

    protected function getCrudParentModel(string $model, ?string $suggestedParent = null): ?string
    {
        $parent = $this->option('parent');
        if (!$parent) {
            $options = $this->guessCrudParentModels($model);
            if (empty($options)) return null; // No parent models
            $parent = $this->confirmCrudParentModel($options, $suggestedParent);
        }

        $parent = $this->qualifyModel($parent);
        if (class_exists($parent)) return $parent;

        $this->fail("Model class {$parent} does not exist.");
    }

    private function guessCrudParentModels(string $model): array
    {
        $parents = [];
        $table = (new $model)->getTable();
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            $foreignTable = $foreignKey['foreign_table'];
            $parents[] = Str::studly(Str::singular($foreignTable));
        }
        return $parents;
    }

    private function confirmCrudParentModel(array $options, ?string $suggestedParent = null): string
    {
        return suggest(
            label: 'Parent model class name:',
            options: $options,
            default: $suggestedParent ?? $options[0] ?? '',
            required: 'Parent model class name is required.',
            hint: $this->type . ' will be generated using this parent model.',
            transform: fn(string $value) => $this->qualifyModel($value),
            validate: function (string $value) {
                return class_exists($value) ? null : "Model $value does not exist.";
            }
        );
    }

    protected function getCrudRoutePrefix(string $model, ?string $parent = null, ?string $prefix = null): string
    {
        $routePrefix = $this->option('routeprefix') ?? $this->option('prefix');
        if (!$routePrefix) {
            if (!$prefix) {
                $prefix = $this->modelToPrefix($model);
                if ($parent) $prefix = $this->modelToPrefix($parent) . '.' . $prefix;
            }
            $routePrefix = $this->confirmCrudRoutePrefix($prefix, $parent !== null);
        }
        return $routePrefix;
    }

    private function confirmCrudRoutePrefix(string $routePrefix, bool $nested): string
    {
        return text(
            label: 'Route prefix:',
            placeholder: 'E.g. photos or users.photos',
            default: $routePrefix,
            required: 'Route prefix is required.',
            hint: $this->type . ' will use this route prefix.',
            validate: fn(string $value) => match (true) {
                Str::startsWith($value, '.') => 'Route prefix should not start with a period.',
                Str::endsWith($value, '.') => 'Route prefix should not end with a period.',
                $nested && !Str::contains($value, '.') => 'Nested route prefix must contain at least one period.',
                default => null,
            }
        );
    }

    private function getCrudViewPrefix(string $model, ?string $parent = null, ?string $prefix = null): string
    {
        $viewPrefix = $this->option('viewprefix') ?? $this->option('prefix');
        if (!$viewPrefix) {
            if (!$prefix) {
                $prefix = $this->modelToPrefix($model);
                if ($parent) $prefix = $this->modelToPrefix($parent) . '.' . $prefix;
            }
            $viewPrefix = $this->confirmCrudViewPrefix($prefix);
        }
        return $viewPrefix;
    }

    private function confirmCrudViewPrefix(string $viewPrefix): string
    {
        return text(
            label: 'View prefix:',
            placeholder: 'E.g. photos or users.photos',
            default: $viewPrefix,
            required: 'View prefix is required.',
            hint: $this->type . ' will use this view prefix.',
            validate: fn(string $value) => match (true) {
                Str::startsWith($value, '.') => 'View prefix should not start with a period.',
                Str::endsWith($value, '.') => 'View prefix should not end with a period.',
                default => null,
            }
        );
    }

    protected function getCrudTable(string $model): string
    {
        $table = $this->option('table');
        if (!$table) {
            $table = Str::snake(Str::pluralStudly(class_basename($model)));
            // only if table does not exist, ask user for it
            if (!Schema::hasTable($table)) {
                $table = $this->confirmCrudTable($table);
            }
        }
        return $table;
    }

    private function confirmCrudTable(string $table): string
    {
        return text(
            label: 'Table name:',
            placeholder: 'E.g. photos or users_photos',
            default: $table,
            required: 'Table name is required.',
            hint: $this->type . ' will use this table.',
            validate: fn(string $value) => match (true) {
                !Schema::hasTable($value) => 'Table does not exist.',
                default => null,
            }
        );
    }

    private function modelToPrefix(?string $model): string
    {
        if (!$model) return null;
        $prefix = Str::lower(class_basename($model));
        $prefix = Str::plural($prefix);
        return $prefix;
    }

    protected function isAuthenticatableModel(string $model): bool
    {
        $config = $this->laravel['config'];
        $providers = $config->get('auth.providers');

        foreach ($providers as $provider) {
            if ($provider['model'] === $model)
                return true;
        }

        return false;
    }

    protected function getVisibleFields($modelClass, array $ignore = []): array
    {
        /** @var Model $modelObject */
        $modelObject = new $modelClass();
        $visible = [];
        $fields = $modelObject->getVisible();
        $hidden = $modelObject->getHidden();

        $table = $modelObject->getTable();
        $columns = ColumnInfo::fromTable($table);
        if (empty($fields)) {
            $fields = array_keys($columns);
        }

        foreach ($fields as $field) {
            if (!in_array($field, $hidden) && !in_array($field, $ignore)) {
                $visible[$field] = $columns[$field];
            }
        }
        return $visible;
    }

    protected function copyDir(string $sourceDir, string $destinationDir, bool $overwrite = false)
    {
        if (!file_exists($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $files = scandir($sourceDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (!$overwrite && file_exists($destinationDir . $file)) continue;
            copy($sourceDir . $file, $destinationDir . $file);
        }
    }
}
