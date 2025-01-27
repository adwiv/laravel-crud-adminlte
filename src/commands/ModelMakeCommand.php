<?php

namespace Adwiv\Laravel\CrudGenerator\Commands;

use Adwiv\Laravel\CrudGenerator\ColumnInfo;
use Adwiv\Laravel\CrudGenerator\CrudHelper;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ModelMakeCommand extends GeneratorCommand
{
    use CrudHelper;

    protected $name = 'crud:model';
    protected $description = 'Create a new Eloquent model class';
    protected $type = 'Model';

    private bool $authenticatable = false;

    protected function getStub(): string
    {
        return $this->authenticatable
            ? $this->resolveStubPath('/stubs/model/user.stub')
            : ($this->option('pivot')
                ? $this->resolveStubPath('/stubs/model/pivot.stub')
                : $this->resolveStubPath('/stubs/model/model.stub'));
    }

    protected function buildClass($name)
    {
        $modelFullName = $this->qualifyModel($name);
        $modelBaseName = class_basename($modelFullName);
        $this->authenticatable = $this->isAuthenticatableModel($modelFullName);

        $TABLE = "";
        $table = $this->getCrudTable($name);
        $defaultTable = Str::snake(Str::pluralStudly($modelBaseName));

        if ($table !== $defaultTable) {
            $TABLE = "\n    protected \$table = '$table';\n";
        }

        $columns = ColumnInfo::fromTable($table);

        $BELONGS = "";
        $HASMANY = "";
        $CASTS = "";
        $IMPORTS = "";
        $UNIQUES = "";
        $TRAITS = "";

        foreach (array_keys($columns) as $field) {
            if ($field == 'id') {
                $column = $columns[$field];
                if ($column->isUuid()) {
                    $IMPORTS .= "use Illuminate\Database\Eloquent\Concerns\HasUuids;\n";
                    $TRAITS .= ", HasUuids";
                }
                if ($column->isUlid()) {
                    $IMPORTS .= "use Illuminate\Database\Eloquent\Concerns\HasUlids;\n";
                    $TRAITS .= ", HasUlids";
                }
            }
            if (in_array($field, ['id', 'uid', 'uuid', 'remember_token', 'deleted_at', 'updated_at', 'created_at'])) continue;

            /** @var ColumnInfo $column */
            $column = $columns[$field];

            if ($column->unique) {
                $findMethod = "findBy" . Str::studly($field);
                $findVariable = Str::camel($field);

                $UNIQUES .= "
    public function $findMethod(\$$findVariable): ?self
    {
        return self::where('$field', \$$findVariable)->first();
    }

    public function {$findMethod}OrFail(\$$findVariable): self
    {
        return self::where('$field', \$$findVariable)->firstOrFail();
    }";
            }

            if ($column->foreign) {
                list($foreignTable, $foreignKey) = explode(',', $column->foreign);
                $relation = Str::camel(Str::singular($foreignTable));
                $relationClass = Str::studly($relation);
                $IMPORTS .= "use " . $this->qualifyModel($relationClass) . ";\n";
                if (strpos($IMPORTS, 'Illuminate\Database\Eloquent\Relations\BelongsTo;') === false) {
                    $IMPORTS .= "use Illuminate\Database\Eloquent\Relations\BelongsTo;\n";
                }
                $BELONGS .= "
    public function $relation(): BelongsTo
    {
        return \$this->belongsTo($relationClass::class, '$field', '$foreignKey');
    }
";
            }

            $enumName = $this->getEnum($table, $field) ?? Str::studly(Str::singular($field));
            $enumClass = $this->qualifyClassForType($enumName, 'Enum');

            if ($column->type == 'enum' && class_exists($enumClass, true)) {
                $IMPORTS .= "use $enumClass;\n";
                $CASTS .= "            '$field' => $enumName::class,\n";
            } else if ($column->type == 'set') {
                if (class_exists($enumClass, true)) {
                    $IMPORTS .= "use App\Casts\CsvAsArray;\n";
                    $IMPORTS .= "use $enumClass;\n";
                    $CASTS .= "            '$field' => CsvAsArray::of($enumName::class),\n";
                } else {
                    $IMPORTS .= "use App\Casts\CsvAsArray;\n";
                    $CASTS .= "            '$field' => CsvAsArray::class,\n";
                }
            } else if ($castType = $column->castType()) {
                $CASTS .= "            '$field' => '$castType',\n";
            }
        }

        $relations = ColumnInfo::getReferencingKeys($table);
        foreach ($relations as $relation) {
            $foreignTable = $relation['table'];
            $foreignKey = $relation['key'];
            $field = $relation['ref'];
            $oneOrMany = $relation['unique'] ? 'hasOne' : 'hasMany';
            $oneOrManyClass = $relation['unique'] ? 'HasOne' : 'HasMany';
            $relation = Str::camel(Str::singular($foreignTable));
            $relationClass = Str::studly($relation);
            $IMPORTS .= "use " . $this->qualifyModel($relationClass) . ";\n";
            $oneOrManyFullClass = 'Illuminate\Database\Eloquent\Relations\\' . $oneOrManyClass . ';';
            if (strpos($IMPORTS, $oneOrManyFullClass) === false) {
                $IMPORTS .= "use $oneOrManyFullClass\n";
            }
            $HASMANY .= "
    public function $relation(): $oneOrManyClass
    {
        return \$this->$oneOrMany($relationClass::class, '$foreignKey', '$field');
    }
";
        }

        $CASTS = trim($CASTS);
        if (!empty($CASTS)) {
            $CASTS = trim("
    protected function casts(): array
    {
        return [
            $CASTS
        ];
    }
") . PHP_EOL;
        }

        $replace = [
            '{{ namespacedModel }}' => $modelFullName,
            '{{ model }}' => $modelBaseName,
            '{{ BELONGS }}' => trim($BELONGS),
            '{{ CASTS }}' => $CASTS,
            '{{ IMPORTS }}' => trim($IMPORTS),
            '{{ UNIQUES }}' => trim($UNIQUES),
            '{{ HASMANY }}' => trim($HASMANY),
            '{{ TABLE }}' => $TABLE,
            '{{ TRAITS }}' => $TRAITS,
        ];

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite if file exists'],
            ['quiet', 'q', InputOption::VALUE_NONE, 'Do not output info messages.'],
            ['table', 't', InputOption::VALUE_REQUIRED, 'Table to use to generate the model'],
            ['pivot', null, InputOption::VALUE_NONE, 'Indicates if the generated model should be a custom intermediate table model'],
        ];
    }
}
