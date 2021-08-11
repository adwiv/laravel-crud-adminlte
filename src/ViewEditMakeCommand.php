<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ViewEditMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'crud:view-edit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new create/edit view';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'EditView';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/views/edit.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param string $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__ . $stub;
    }

    /**
     * Parse the class name and format according to the root namespace.
     *
     * @param string $name
     * @return string
     */
    protected function qualifyClass($name)
    {
        return $name;
    }

    /**
     * Get the destination class path.
     *
     * @param string $name
     * @return string
     */
    protected function getPath($name)
    {
        $dir = $this->laravel->resourcePath('views');
        return "$dir/$name-edit.blade.php";
    }

    protected function buildClass($name)
    {
        if (!($model = $this->option('model'))) {
            $model = Str::studly(class_basename($name));
        }

        // Copy the scripts for use in file
        $this->copyBladeScripts();

        $modelClass = $this->parseModel($model);
        /** @var Model $modelObject */
        $modelObject = new $modelClass();
        $fields = $modelObject->getVisible();
        $hidden = $modelObject->getHidden();
        if (empty($fields)) {
            $table = $modelObject->getTable();
            $columns = ColumnInfo::fromTable($table);
            $fields = array_keys($columns);
        }
        $FIELDS = "";
        $count = 0;
        $modelVariable = lcfirst(class_basename($modelClass));
        foreach ($fields as $field) {
            if (in_array($field, ['id', 'uid', 'created_at', 'updated_at', 'deleted_at'])) continue;
            $fieldName = ucwords(str_replace('_', ' ', Str::snake($field)));
            if (!in_array($field, $hidden)) {
                $FIELDS .=
                    "                    <x-adminlte-input name=\"$field\" label=\"$fieldName\" disable-feedback required\n" .
                    "                                      value=\"{{ old('$field', \$$modelVariable->$field ?? '') }}\" \n" .
                    "                                      label-class=\"text-sm\" fgroup-class=\"col-sm-6 col-lg-4 col-xl-3\">\n" .
                    "                    </x-adminlte-input>\n";
                $count++;
            }
        }

        $replace = [
            '{{ namespacedModel }}' => $modelClass,
            '{{ model }}' => class_basename($modelClass),
            '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
            '{{ pluralModel }}' => Str::plural(class_basename($modelClass)),
            '{{ pluralModelVariable }}' => Str::plural(lcfirst(class_basename($modelClass))),
            '{{ FIELDS }}' => trim($FIELDS),
        ];

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    /**
     * Copy the script stubs to view directory
     */
    protected function copyBladeScripts()
    {
        $dir = $this->laravel->resourcePath('views/scripts/');
        if (!file_exists($dir)) mkdir($dir);
        $files = ["form-slugify.blade.php", "form-validate.blade.php"];
        foreach ($files as $file) {
            if (!file_exists("$dir$file"))
                copy($this->resolveStubPath("/stubs/views/scripts/$file"), "$dir$file");
        }
    }

    /**
     * Get the fully-qualified model class name.
     *
     * @param string $model
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function parseModel($model)
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new \InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Overwrite if file exists.'],
            ['model', null, InputOption::VALUE_REQUIRED, 'Model to use for getting attributes.'],
        ];
    }
}
