<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class RequestMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'crud:request';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new form request class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Request';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/request.stub');
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
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Http\Requests';
    }

    protected function buildClass($name)
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

        $modelClass = $this->parseModel($model);
        /** @var Model $modelObject */
        $modelObject = new $modelClass();
        $table = $modelObject->getTable();
        $columns = ColumnInfo::fromTable($table);

        $fillable = [];
        $RULES = "";
        $MESSAGES = "";
        foreach (array_keys($columns) as $field) {
            if (in_array($field, ['id', 'uid', 'uuid', 'remember_token', 'deleted_at', 'updated_at', 'created_at'])) continue;

            if ($modelObject->isFillable($field)) {
                $fillable[] = $field;
                /** @var ColumnInfo $column */
                $column = $columns[$field];
                $required = $column->notNull ? 'required' : 'nullable';
                $type = $column->validationType();
                $min = $column->unsigned ? '|min:0' : '';
                $max = $column->length > 0 ? '|max:' . $column->length : '';
                $unique = $column->unique ? "|unique:$table,$field{\$ignoreId}" : '';
                $exists = $column->foreign ? "|exists:$column->foreign" : '';
                $RULES .= "            '$field' => \"$required|$type$min$max$unique$exists\",\n";
                $MESSAGES .= "            //'$field' => '',\n";
            }
        }

        $replace = [
            '{{ namespacedModel }}' => $modelClass,
            '{{ model }}' => class_basename($modelClass),
            '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
            '{{ pluralModelVariable }}' => Str::plural(lcfirst(class_basename($modelClass))),
            '{{ RULES }}' => trim($RULES),
            '{{ MESSAGES }}' => trim($MESSAGES),
        ];

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
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
