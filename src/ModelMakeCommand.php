<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ModelMakeCommand extends GeneratorCommand
{
    use ClassHelper;

    protected $name = 'crud:model';
    protected $description = 'Create a new Eloquent model class';
    protected $type = 'Model';

    protected function getStub(): string
    {
        return $this->option('pivot')
            ? $this->resolveStubPath('/stubs/model/pivot.stub')
            : $this->resolveStubPath('/stubs/model/model.stub');
    }

    protected function buildClass($name)
    {
        $modelClass = $this->fullModelClass($name);

        $TABLE = "";
        if (!($table = $this->option('table'))) {
            $table = Str::snake(Str::pluralStudly(class_basename($name)));
        } else {
            $TABLE = "\n    protected \$table = '$table';\n";
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

    public function {$findMethod}OrFail(\$$findVariable)
    {
        return self::where('$field', \$$findVariable)->firstOrFail();
    }";
            }

            if ($column->foreign) {
                list($foreignTable, $foreignKey) = explode(',', $column->foreign);
                $relation = Str::singular($foreignTable);
                $relationClass = Str::studly($relation);
                $IMPORTS .= "use " . $this->fullModelClass($relationClass) . ";\n";

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
            $IMPORTS .= "use " . $this->fullModelClass($relationClass) . ";\n";

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
            '{{ TABLE }}' => ($TABLE),
        ];

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Overwrite if file exists'],
            ['table', null, InputOption::VALUE_REQUIRED, 'Table to use to generate the model'],
            ['pivot', 'p', InputOption::VALUE_NONE, 'Indicates if the generated model should be a custom intermediate table model'],
        ];
    }
}
