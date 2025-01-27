<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\text;

trait CrudHelper
{
    private $enumList = [];

    public function getEnum(string $table, string $column): ?string
    {
        return $this->enumList["$table::$column"] ?? null;
    }

    public function addEnum(string $table, string $column, string $enum)
    {
        $this->enumList["$table::$column"] = $enum;
    }

    public function info($string, $verbosity = null)
    {
        if ($this->hasOption('quiet') && $this->option('quiet')) return;
        parent::info($string, $verbosity);
    }

    public function handle()
    {
        $filename = $this->getNameInput();

        if ($this->alreadyExists($filename)) {

            $forceOverwrite = $this->hasOption('force') && $this->option('force');
            if (!$forceOverwrite) {
                if ($this->hasOption('skip') && $this->option('skip')) {
                    return;
                }
                if (!confirm("{$this->type} $filename already exists. Do you want to overwrite it?", false)) {
                    return false;
                }
                $this->input->setOption('force', true);
            }
        }

        return parent::handle();
    }

    protected function baseNamespace(): string
    {
        return trim($this->laravel->getNamespace(), '\\');
    }

    protected function qualifyClassForType(string $name, string $type)
    {
        if (!in_array($type, ['Enum', 'Request', 'Resource', 'Controller', 'Model'])) $this->fail("Unknown class type '$type'.");

        $oldType = $this->type;
        $this->type = $type;
        $qualified = $this->qualifyClass($name);
        $this->type = $oldType;
        return $qualified;
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        $type = $this->type;
        if ($type == 'View') return $rootNamespace . '\Views';
        if ($type == 'Enum') return $rootNamespace . '\Enums';
        if ($type == 'Model') return $rootNamespace . '\Models';
        if ($type == 'Request') return $rootNamespace . '\Http\Requests';
        if ($type == 'Resource') return $rootNamespace . '\Http\Resources';
        if ($type == 'Controller') return $rootNamespace . '\Http\Controllers';
        $this->fail("Unknown class type '$this->type'.");
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
        if (!class_exists($model)) $this->fail("Model class {$model} does not exist.");

        $this->info("Using model {$model} for this $this->type.");
        return $model;
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

    protected function getCrudControllerType(string $model): string
    {
        if ($this->option('regular') && ($this->option('parent') || $this->option('shallow') || $this->option('nested'))) {
            $this->fail("Cannot use --regular option with --parent, --shallow or --nested options.");
        }

        $controllerType = $this->option('regular') ? 'regular' : null;
        $controllerType ??= $this->option('nested') ? 'nested' : null;
        $controllerType ??= $this->option('shallow') ? 'shallow' : null;

        $options = ['regular' => 'Regular', 'nested' => 'Nested', 'shallow' => 'Shallow'];
        $defaultOption = 'regular';

        if (!$controllerType && !$this->option('parent')) {
            $parents = $this->guessCrudParentModels($model);
            if (empty($parents)) $controllerType = 'regular';
        }

        if (!$controllerType && $this->option('parent')) {
            unset($options['regular']);
            $defaultOption = 'shallow';
        }

        $controllerType ??= select(
            label: 'Controller Resource type:',
            options: $options,
            default: $defaultOption,
        );

        $this->info("Using $controllerType controller routes for this $this->type.");
        return $controllerType;
    }

    protected function getCrudParentModel(string $model, ?string $suggestedParent = null): ?string
    {
        if ($this->hasOption('no-parent') && $this->option('no-parent')) return null;

        $parent = $this->option('parent');
        if (!$parent) {
            $options = $this->guessCrudParentModels($model);
            if (empty($options)) return null; // No parent models
            $parent = $this->confirmCrudParentModel($options, $suggestedParent);
        }

        $parent = $this->qualifyModel($parent);
        if (!class_exists($parent)) $this->fail("Model class {$parent} does not exist.");

        $this->info("Using parent model {$parent} for this $this->type.");

        return $parent;
    }

    protected function guessCrudParentModels(string $model): array
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

        $this->info("Using route prefix {$routePrefix} for this $this->type.");
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

        $this->info("Using view prefix {$viewPrefix} for this $this->type.");
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
            $table = class_exists($model, true) ?
                (new $model)->getTable() :
                Str::snake(Str::pluralStudly(class_basename($model)));

            // only if table does not exist, ask user for it
            if (!Schema::hasTable($table)) {
                $table = $this->confirmCrudTable($table);
            }
        }

        $this->info("Using table `$table` for this $this->type");
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

    protected function getCrudEnumColumn(string $table)
    {
        $enumColumns = array_keys(ColumnInfo::getEnumColumns($table));
        if (empty($enumColumns)) $this->fail("Table $table does not have any enum or set columns.");

        $column = $this->option('column') ?? select(
            label: 'Enum column:',
            options: $enumColumns,
            default: $enumColumns[0],
        );

        if (!in_array($column, $enumColumns)) $this->fail("Column `$column` is not an `enum` or `set` column in `$table` table.");

        $this->info("Using column `$column` for this $this->type.");
        return $column;
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
        $sourceDir = rtrim($sourceDir, '/') . '/';
        $destinationDir = rtrim($destinationDir, '/') . '/';

        if (!file_exists($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $files = scandir($sourceDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (!$overwrite && file_exists($destinationDir . $file)) continue;
            copy($sourceDir . $file, $destinationDir . $file);
            $this->info("Copied $file to $destinationDir");
        }
    }

    protected function copyFile(string $file, string $sourceDir, string $destinationDir, bool $overwrite = false)
    {
        $sourceDir = rtrim($sourceDir, '/') . '/';
        $destinationDir = rtrim($destinationDir, '/') . '/';

        if (!file_exists($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        if (!$overwrite && file_exists($destinationDir . $file)) return;
        copy($sourceDir . $file, $destinationDir . $file);
        $this->info("Copied $file to $destinationDir");
    }

    public function confirmEnumName(?string $name = null, ?string $column = null): string
    {
        $name ??= Str::studly(Str::singular($column));
        return text(
            label: 'Enum class name:',
            placeholder: 'E.g. Gender',
            default: $name ?? '',
            required: 'Enum class name is required.',
        );
    }
}
