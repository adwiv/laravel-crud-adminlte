<?php

namespace Adwiv\Laravel\CrudGenerator\Console;

use Adwiv\Laravel\CrudGenerator\ColumnInfo;
use Adwiv\Laravel\CrudGenerator\CrudHelper;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;

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
        $force = $this->option('force');
        $this->copyFile('CsvArray.php', __DIR__ . '/../stubs/casts', $this->laravel->path . '/Casts');

        $modelFullName = $this->qualifyModel($name);
        $modelBaseName = class_basename($modelFullName);
        $this->authenticatable = $this->isAuthenticatableModel($modelFullName);

        $table = $this->getCrudTable($name, false);
        $defaultTable = Str::snake(Str::pluralStudly($modelBaseName));

        // Begin:: Generate Enums for the model
        $enumColumns = ColumnInfo::getEnumColumns($table);
        foreach ($enumColumns as $column) {
            $enumFieldName = $column->name;
            $values = $column->values;
            $this->info('');
            $this->info("Found enum column `$enumFieldName` in `$table` table with values: " . implode(', ', $values));

            $enumName = Str::studly(Str::singular($enumFieldName));
            $enumClass = $this->qualifyClassForType($enumName, 'Enum');
            $enumExists = $this->alreadyExists($enumClass);

            if (confirm("Do you want to generate an enum for `$enumFieldName` column?", !$enumExists)) {
                $enumName = $this->confirmEnumName(null, $enumFieldName);
                $this->addEnum($table, $enumFieldName, $enumName);
                $this->call('crud:enum', ['name' => $enumName, '--table' => $table, '--column' => $enumFieldName, '--quiet' => true, '--force' => $force]);
            }
        }
        // End:: Generate Enums for the model

        $TABLE = "";

        if ($table !== $defaultTable) {
            $TABLE = "\n    protected \$table = '$table';\n";
        }

        $columns = ColumnInfo::fromTable($table);

        $BELONGS = "";
        $HASMANY = "";
        $CASTS = "";
        $IMPORTS = array();
        $UNIQUES = "";
        $TRAITS = "";

        // Begin:: Generate fields for the model
        /** @var ColumnInfo $column */
        foreach ($columns as $field => $column) {
            if ($field == 'id') {
                if ($column->isUuid()) {
                    $IMPORTS[] = "use Illuminate\Database\Eloquent\Concerns\HasUuids;";
                    $TRAITS .= ", HasUuids";
                }
                if ($column->isUlid()) {
                    $IMPORTS[] = "use Illuminate\Database\Eloquent\Concerns\HasUlids;";
                    $TRAITS .= ", HasUlids";
                }
            }
            if (in_array($field, ['id', 'uid', 'uuid', 'remember_token', 'deleted_at', 'updated_at', 'created_at'])) continue;

            // Begin:: Generate casts for the model
            $enumName = $this->getEnum($table, $field) ?? Str::studly(Str::singular($field));
            $enumClass = $this->qualifyClassForType($enumName, 'Enum');

            if ($column->type == 'enum' && class_exists($enumClass)) {
                $IMPORTS[] = "use $enumClass;";
                $CASTS .= "            '$field' => $enumName::class,\n";
            } else if ($column->type == 'set') {
                if (class_exists($enumClass)) {
                    $IMPORTS[] = "use App\Casts\CsvArray;";
                    $IMPORTS[] = "use $enumClass;";
                    $CASTS .= "            '$field' => CsvArray::of($enumName::class),\n";
                } else {
                    $IMPORTS[] = "use App\Casts\CsvArray;";
                    $CASTS .= "            '$field' => CsvArray::class,\n";
                }
            } else if ($castType = $column->castType()) {
                $CASTS .= "            '$field' => '$castType',\n";
            }
            // End:: Generate casts for the model

            // Begin:: Generate unique methods for the model
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
    }
";
            }
            // End:: Generate unique methods for the model

            // Begin:: Generate belongsTo methods for the model
            if ($column->foreign) {
                list($foreignTable, $foreignKey) = explode(',', $column->foreign);
                $relationClass = $this->getModelForTable($foreignTable);
                $relationBaseClass = class_basename($relationClass);
                $relationName = Str::camel(Str::replaceEnd("_$foreignKey", '', $field));
                $this->debug("$field, $foreignTable, $foreignKey, $relationBaseClass, $relationName");

                $IMPORTS[] = "use $relationClass;";
                $IMPORTS[] = "use Illuminate\Database\Eloquent\Relations\BelongsTo;";
                $BELONGS .= "
    public function $relationName(): BelongsTo
    {
        return \$this->belongsTo($relationBaseClass::class, '$field', '$foreignKey');
    }
";
            }
            // End:: Generate belongsTo methods for the model
        }
        // End of field loop

        // Create HasOne and HasMany relations
        $relations = ColumnInfo::getReferencingKeys($table);
        foreach ($relations as $relation) {
            $foreignTable = $relation['table'];
            $foreignKey = $relation['key'];
            $localKey = $relation['ref'];
            $oneOrMany = $relation['unique'] ? 'hasOne' : 'hasMany';
            $oneOrManyClass = $relation['unique'] ? 'HasOne' : 'HasMany';
            $localTableRef = Str::lower(Str::replaceEnd("_$localKey", '', $foreignKey));
            $localTableName = Str::plural($localTableRef);
            $relationClass = $this->getModelForTable($foreignTable);
            $relationBaseClass = class_basename($relationClass);

            $relationName = $relation['unique'] ? Str::singular($foreignTable) : $foreignTable;
            if ($localTableName !== $table) {
                $relationName = "{$localTableRef}_{$relationName}";
            } else if (Str::startsWith($relationName, "{$localTableRef}_")) {
                $relationName = Str::replaceFirst("{$localTableRef}_", '', $relationName);
            }
            $relationName = Str::camel($relationName);

            $this->debug("$foreignTable, $foreignKey, $localKey, $localTableRef, $localTableName, $relationName");

            $IMPORTS[] = "use $relationClass;";
            $oneOrManyFullClass = 'Illuminate\Database\Eloquent\Relations\\' . $oneOrManyClass . ';';
            $IMPORTS[] = "use $oneOrManyFullClass;";
            $HASMANY .= "
    public function $relationName(): $oneOrManyClass
    {
        return \$this->$oneOrMany($relationBaseClass::class, '$foreignKey', '$localKey');
    }
";
        }

        // Create casts method if casts are not empty
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
            '{{ CASTS }}' => trim($CASTS),
            '{{ IMPORTS }}' => implode("\n", array_unique($IMPORTS)),
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
            ['table', 't', InputOption::VALUE_REQUIRED, 'Table to use to generate the model'],
            ['pivot', null, InputOption::VALUE_NONE, 'Indicates if the generated model should be a custom intermediate table model'],
        ];
    }
}
