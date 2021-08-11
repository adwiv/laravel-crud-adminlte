<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

abstract class ViewBaseMakeCommand extends GeneratorCommand
{
    use ClassHelper;

    protected $viewType = 'undefined';

    /**
     * Parse the class name and format according to the root namespace.
     */
    protected final function qualifyClass($name): string
    {
        return $name;
    }

    /**
     * Get the destination class path.
     */
    protected final function getPath($name): string
    {
        $dir = $this->laravel->resourcePath('views');
        $name = str_replace('.', '/', $name);
        return "$dir/$name-$this->viewType.blade.php";
    }

    protected final function buildClass($name)
    {
        if (!($model = $this->option('model'))) {
            $model = Str::studly(class_basename(str_replace('.', '/', $name)));
        }

        $modelClass = $this->getModelClass($model);
        /** @var Model $modelObject */
        $modelObject = new $modelClass();
        $fields = $modelObject->getVisible();
        $hidden = $modelObject->getHidden();
        if (empty($fields)) {
            $table = $modelObject->getTable();
            $columns = ColumnInfo::fromTable($table);
            $fields = array_keys($columns);
        }

        $visible = [];
        foreach ($fields as $field) {
            if (in_array($field, ['id', 'uid', 'created_at', 'updated_at', 'deleted_at'])) continue;
            if (!in_array($field, $hidden)) {
                $visible[] = $field;
            }
        }

        $replace = array_merge(
            [
                '{{ namespacedModel }}' => $modelClass,
                '{{ model }}' => class_basename($modelClass),
                '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
                '{{ pluralModel }}' => Str::plural(class_basename($modelClass)),
                '{{ pluralModelVariable }}' => Str::plural(lcfirst(class_basename($modelClass))),
            ],
            $this->buildViewReplacements($modelClass, $visible)
        );

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    protected abstract function buildViewReplacements($modelClass, $fields): array;

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite if file exists.'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Specify Model to use.'],
        ];
    }
}
