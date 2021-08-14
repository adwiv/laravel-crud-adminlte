<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @mixin \Illuminate\Console\Command
 */
trait ClassHelper
{
    protected function baseNamespace(): string
    {
        return trim($this->laravel->getNamespace(), '\\');
    }

    protected function defaultNamespace($rootNamespace, $type = null): string
    {
        $type = $type ?? $this->type;
        if ($type == 'Request') return $rootNamespace . '\Http\Requests';
        if ($type == 'Resource') return $rootNamespace . '\Http\Resources';
        if ($type == 'Collection') return $rootNamespace . '\Http\Resources';
        if ($type == 'Controller') return $rootNamespace . '\Http\Controllers';
        if ($type == 'Model') return is_dir(app_path('Models')) ? $rootNamespace . '\Models' : $rootNamespace;
        throw new \InvalidArgumentException("Unknown class type '$this->type'.");
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $this->defaultNamespace($rootNamespace);
    }

    /**
     * Parse the class name and format according to the root namespace.
     */
    private function fullClassName(string $name, $type = null): string
    {
        $name = ltrim($name, '\\/');

        $name = str_replace('/', '\\', $name);

        $baseNamespace = $this->baseNamespace();

        if (Str::startsWith($name, $baseNamespace)) return $name;

        return $this->fullClassName($this->defaultNamespace($baseNamespace, $type) . '\\' . $name, $type);
    }

    /**
     * Get the fully-qualified model class name.
     */
    protected function fullModelClass(string $model): string
    {
        $this->checkClassName($model);
        return $this->fullClassName($model, 'Model');
    }

    /**
     * Get the fully-qualified request class name.
     */
    protected function fullRequestClass($name): string
    {
        $this->checkClassName($name);
        return $this->fullClassName($name, 'Request');
    }

    /**
     * Get the fully-qualified resource class name.
     */
    protected function fullResourceClass($name): string
    {
        $this->checkClassName($name);
        return $this->fullClassName($name, 'Resource');
    }

    /**
     * Get the fully-qualified controller class name.
     */
    protected function fullControllerClass($name): string
    {
        $this->checkClassName($name);
        return $this->fullClassName($name, 'Controller');
    }

    protected function fullClassPath($name)
    {
        $name = Str::replaceFirst($this->baseNamespace(), '', $name);
        $path = $this->laravel['path'] . '/' . str_replace('\\', '/', $name) . '.php';
        return str_replace('//', '/', $path);
    }

    protected function fullViewPath($name, $viewPrefix, $type): string
    {
        $name = strtolower($name);
        $dir = $this->laravel->resourcePath('views');
        $path = str_replace('.', '/', "$viewPrefix.$name");
        $path = str_replace('//', '/', "/$path");
        return "$dir$path-$type.blade.php";
    }

    protected function prefixWithDot($prefix): string
    {
        return ($prefix = trim($prefix)) ? str_replace('..', '.', "$prefix.") : '';
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath(string $stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__ . $stub;
    }

    private function checkClassName($name)
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $name)) {
            throw new \InvalidArgumentException("Class '$name' contains invalid characters.");
        }
    }

    protected function guessModelName($name)
    {
        if (!($model = $this->option('model'))) {
            $suffix = $this->type;
            $baseLen = strlen($suffix);
            $baseName = class_basename($name);
            if (strlen($baseName) > $baseLen && str_ends_with($baseName, $suffix)) {
                $model = substr($baseName, 0, -$baseLen);
            } else {
                $this->error('Could not guess model name. Please use --model option');
                die();
            }
        }
        return $model;
    }

    protected function getVisibleFields($modelClass, array $ignore = []): array
    {
        /** @var Model $modelObject */
        $modelObject = new $modelClass();
        $visible = [];
        $fields = $modelObject->getVisible();
        $hidden = $modelObject->getHidden();
        if (empty($fields)) {
            $table = $modelObject->getTable();
            $columns = ColumnInfo::fromTable($table);
            $fields = array_keys($columns);
        }

        foreach ($fields as $field) {
            if (!in_array($field, $hidden) && !in_array($field, $ignore)) {
                $visible[] = $field;
            }
        }
        return $visible;
    }
}
