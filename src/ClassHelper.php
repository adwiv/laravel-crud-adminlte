<?php

// namespace Adwiv\Laravel\CrudGenerator;

// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Support\Str;

// /**
//  * @mixin \Illuminate\Console\Command
//  */
// trait ClassHelper
// {
//     /**
//      * Parse the class name and format according to the root namespace.
//      */
//     private function fullClassName(string $name, $type = null): string
//     {
//         $name = ltrim($name, '\\/');

//         $name = str_replace('/', '\\', $name);

//         $baseNamespace = $this->baseNamespace();

//         if (Str::startsWith($name, $baseNamespace)) return $name;

//         return $this->fullClassName($this->defaultNamespace($baseNamespace, $type) . '\\' . $name, $type);
//     }

//     /**
//      * Get the fully-qualified model class name.
//      */
//     protected function fullModelClass(string $model): string
//     {
//         $this->checkClassName($model);
//         return $this->fullClassName($model, 'Model');
//     }

//     /**
//      * Get the fully-qualified request class name.
//      */
//     protected function fullRequestClass($name): string
//     {
//         $this->checkClassName($name);
//         return $this->fullClassName($name, 'Request');
//     }

//     /**
//      * Get the fully-qualified resource class name.
//      */
//     protected function fullResourceClass($name): string
//     {
//         $this->checkClassName($name);
//         return $this->fullClassName($name, 'Resource');
//     }

//     /**
//      * Get the fully-qualified controller class name.
//      */
//     protected function fullControllerClass($name): string
//     {
//         $this->checkClassName($name);
//         return $this->fullClassName($name, 'Controller');
//     }

//     protected function fullClassPath($name)
//     {
//         $name = Str::replaceFirst($this->baseNamespace(), '', $name);
//         $path = $this->laravel['path'] . '/' . str_replace('\\', '/', $name) . '.php';
//         return str_replace('//', '/', $path);
//     }

//     protected function prefixWithDot($prefix): string
//     {
//         return ($prefix = trim($prefix)) ? str_replace('..', '.', "$prefix.") : '';
//     }

//     /**
//      * Resolve the fully-qualified path to the stub.
//      */
//     protected function resolveStubPath(string $stub): string
//     {
//         return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
//             ? $customPath
//             : __DIR__ . $stub;
//     }

//     private function checkClassName($name)
//     {
//         if (preg_match('([^A-Za-z0-9_/\\\\])', $name)) {
//             throw new \InvalidArgumentException("Class '$name' contains invalid characters.");
//         }
//     }

//     protected function guessModelNameOrNull($name): ?string
//     {
//         $suffix = $this->type;
//         $baseLen = strlen($suffix);
//         $baseName = class_basename($name);
//         if (strlen($baseName) > $baseLen && str_ends_with($baseName, $suffix)) {
//             return substr($baseName, 0, -$baseLen);
//         }
//         return null;
//     }

//     protected function guessModelName($name)
//     {
//         if (!($model = $this->option('model'))) {
//             $suffix = $this->type;
//             $baseLen = strlen($suffix);
//             $baseName = class_basename($name);
//             if (strlen($baseName) > $baseLen && str_ends_with($baseName, $suffix)) {
//                 $model = substr($baseName, 0, -$baseLen);
//             } else {
//                 $this->error('Could not guess model name. Please use --model option');
//                 die();
//             }
//         }
//         return $model;
//     }
// }
