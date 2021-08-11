<?php

namespace Adwiv\Laravel\CrudGenerator;

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

    protected function getDefaultNamespace($rootNamespace)
    {
        if ($this->type == 'Request') return $rootNamespace . '\Http\Requests';
        if ($this->type == 'Resource') return $rootNamespace . '\Http\Resources';
        if ($this->type == 'Collection') return $rootNamespace . '\Http\Resources';
        if ($this->type == 'Controller') return $rootNamespace . '\Http\Controllers';
        if ($this->type == 'Model') return is_dir(app_path('Models')) ? $rootNamespace . '\Models' : $rootNamespace;
        throw new \InvalidArgumentException("Unknown class type '$this->type'.");
    }

    /**
     * Parse the class name and format according to the root namespace.
     */
    private function fullClassName(string $name): string
    {
        $name = ltrim($name, '\\/');

        $name = str_replace('/', '\\', $name);

        $baseNamespace = $this->baseNamespace();

        if (Str::startsWith($name, $baseNamespace)) return $name;

        return $this->fullClassName($this->getDefaultNamespace($baseNamespace) . '\\' . $name);
    }

    /**
     * Get the fully-qualified model class name.
     */
    protected function getModelClass(string $model): string
    {
        $this->checkClassName($model);
        return $this->fullClassName($model, 'model');
    }

    /**
     * Get the fully-qualified request class name.
     */
    protected function getRequestClass($name): string
    {
        $this->checkClassName($name);
        return $this->fullClassName($name, 'request');
    }

    /**
     * Get the fully-qualified resource class name.
     */
    protected function getResourceClass($name): string
    {
        $this->checkClassName($name);
        return $this->fullClassName($name, 'resource');
    }

    /**
     * Get the fully-qualified controller class name.
     */
    protected function getControllerClass($name): string
    {
        $this->checkClassName($name);
        return $this->fullClassName($name, 'controller');
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
            }
        }
        return $model;
    }
}
