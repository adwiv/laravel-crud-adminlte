<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ModelMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'crud:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Eloquent model class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Model';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->option('pivot')
            ? $this->resolveStubPath('/stubs/model/pivot.stub')
            : $this->resolveStubPath('/stubs/model/model.stub');
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
        return is_dir(app_path('Models')) ? $rootNamespace . '\\Models' : $rootNamespace;
    }

    protected function buildClass($name)
    {
        $modelClass = $this->parseModel($name);

        if (!($table = $this->option('table'))) {
            $table = Str::snake(Str::pluralStudly(class_basename($name)));
        }

        $columns = ColumnInfo::fromTable($table);

        $BELONGS = "";
        $HASMANY = "";
        $CASTS = "";
        $IMPORTS = "";
        $UNIQUES = "";

        foreach (array_keys($columns) as $field) {
            if (in_array($field, ['id', 'uid', 'uuid', 'remember_token', 'deleted_at', 'updated_at', 'created_at'])) continue;

            /** @var ColumnInfo $column */
            $column = $columns[$field];

            if ($column->unique) {
                $findMethod = "findBy" . Str::studly($field);
                $findVariable = Str::camel($field);

                $UNIQUES .= "
    public function $findMethod(\$$findVariable)
    {
        return self::where('$field', \$$findVariable)->first();
    }
";
            }

            if ($column->foreign) {
                list($foreignTable, $foreignKey) = explode(',', $column->foreign);
                $relation = Str::singular($foreignTable);
                $relationClass = Str::studly($relation);

                $BELONGS .= "
    public function $relation()
    {
        return \$this->belongsTo($relationClass::class, '$foreignKey', '$field');
    }
";
            }

            if ($castType = $column->castType()) {
                $CASTS .= "        '$field' => '$castType',\n";
            }
        }

        $relations = ColumnInfo::getReferencingKeys($table);
        foreach ($relations as $relation) {
            $foreignTable = $relation['table'];
            $foreignKey = $relation['key'];
            $field = $relation['ref'];
            $oneOrMany = $relation['unique'] ? 'hasOne' : 'hasMany';
            $relation = Str::singular($foreignTable);
            $relationClass = Str::studly($relation);

            $HASMANY .= "
    public function $relation()
    {
        return \$this->$oneOrMany($relationClass::class, '$foreignKey', '$field');
    }
";
        }

        $replace = [
            '{{ namespacedModel }}' => $modelClass,
            '{{ model }}' => class_basename($modelClass),
            '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
            '{{ pluralModelVariable }}' => Str::plural(lcfirst(class_basename($modelClass))),
            '{{ BELONGS }}' => trim($BELONGS),
            '{{ CASTS }}' => trim($CASTS),
            '{{ IMPORTS }}' => trim($IMPORTS),
            '{{ UNIQUES }}' => trim($UNIQUES),
            '{{ HASMANY }}' => trim($HASMANY),
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
            ['force', null, InputOption::VALUE_NONE, 'Overwrite if file exists'],
            ['table', null, InputOption::VALUE_REQUIRED, 'Table to use to generate the model'],
            ['pivot', 'p', InputOption::VALUE_NONE, 'Indicates if the generated model should be a custom intermediate table model'],
        ];
    }
}
