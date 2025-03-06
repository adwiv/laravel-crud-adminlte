<?php

namespace Adwiv\Laravel\CrudGenerator\Console;

use Adwiv\Laravel\CrudGenerator\ColumnInfo;
use Adwiv\Laravel\CrudGenerator\CrudHelper;
use BackedEnum;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class RequestMakeCommand extends GeneratorCommand
{
    use CrudHelper;

    protected $name = 'crud:request';
    protected $description = 'Create a new form request class';
    protected $type = 'Request';

    private bool $unique = false;

    protected function getStub(): string
    {
        $file = $this->unique ? 'unique.stub' : 'request.stub';
        return $this->resolveStubPath("/stubs/requests/$file");
    }

    protected function buildClass($name)
    {
        // Deduce the model name
        $modelFullName = $this->getCrudModel($name);
        $modelBaseName = class_basename($modelFullName);

        /** @var Model $modelInstance */
        $modelInstance = new $modelFullName();
        $modelCasts = $modelInstance->getCasts();

        $table = $modelInstance->getTable();
        $columns = ColumnInfo::fromTable($table);

        // Get the resource type
        $resourceType = $this->getCrudControllerType($table);

        $parentIdColumn = null;
        if ($resourceType != 'regular') {
            $parentFullName = $this->getCrudParentModel($table);
            if ($parentFullName) {
                $parentTable = (new $parentFullName)->getTable();
                $foreignColumns = ColumnInfo::getForeignColumns($table);
                $parentIdColumn = array_search("$parentTable,id", $foreignColumns, true);
                if (!$parentIdColumn) $this->warn('Table does not have an parent id column. ');
            }
        }

        $RULES = "";
        $MESSAGES = "";
        $IMPORTS = array();
        foreach (array_keys($columns) as $field) {
            $ignore = ['id', 'uid', 'uuid', 'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at'];
            if (in_array($field, $ignore)) continue;

            if ($modelInstance->isFillable($field)) {
                if ($field == $parentIdColumn) continue;
                $castType = $modelCasts[$field] ?? null;

                $rules = [];

                /** @var ColumnInfo $column */
                $column = $columns[$field];
                if ($column->unique) $this->unique = true;
                $validationType = $column->validationType();
                $isString = $validationType == 'string';

                $rules[] = $column->notNull ? "'required'" : "'nullable'";
                $rules[] = "'{$validationType}'";

                if ($column->unsigned) $rules[] = "'min:0'";
                if ($isString && $column->length > 0) $rules[] = "'max:{$column->length}'";
                if ($column->foreign) $rules[] = "'exists:{$column->foreign}'";
                if ($column->unique) $rules[] = "\"unique:{$table},{$field}{\$ignoreId}\"";

                $setRule = null;
                if ($column->type == 'enum' || $column->type == 'set') {
                    $isEnum = $column->type == 'enum';
                    $enumRule = null;

                    if ($castType) {
                        $enumClass = last(explode(':', $castType));
                        $isBackedEnum = is_subclass_of($enumClass, BackedEnum::class);
                        if ($isBackedEnum) {
                            $IMPORTS[] = "use $enumClass;";
                            $enumBaseClass = class_basename($enumClass);
                            $enumRule = "Rule::enum($enumBaseClass::class)";
                        }
                    }

                    $enumRule ??= "'in:" . implode(',', $column->values) . "'";
                    if ($isEnum) $rules[] = $enumRule;
                    else $setRule = $enumRule;
                }

                $rules = implode(", ", $rules);
                $RULES .= "            '$field' => [$rules],\n";
                if ($setRule) $RULES .= "            '$field.*' => ['required', 'string', $setRule],\n";
                $MESSAGES .= "            //'$field' => '',\n";
            }
        }

        $routePrefix = $this->option('routeprefix') ?? $this->option('prefix');
        $routePrefix ??= Str::plural(lcfirst($modelBaseName));
        $routePrefixParts = explode('.', $routePrefix);
        $modelRoutePrefix = array_pop($routePrefixParts);

        $replace = [
            '{{ RULES }}' => trim($RULES),
            '{{ MESSAGES }}' => trim($MESSAGES),
            '{{ IMPORTS }}' => implode("\n", array_unique($IMPORTS)),
        ];

        $replace = $this->buildModelReplacements($replace, $modelFullName, $modelBaseName, $modelRoutePrefix);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    /**
     * Build the model replacement values.
     */
    protected function buildModelReplacements(array $replace, string $modelFullName, string $modelBaseName, string $modelRoutePrefix): array
    {
        return array_merge($replace, [
            '{{ namespacedModel }}' => $modelFullName,
            '{{ model }}' => $modelBaseName,
            '{{ modelVariable }}' => Str::singular($modelRoutePrefix),
        ]);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite if file exists.'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Model to use for getting attributes.'],
            ['parent', 'p', InputOption::VALUE_REQUIRED, 'Parent model to use for getting attributes.'],
            ['regular', null, InputOption::VALUE_NONE, 'Generate a regular controller.'],
            ['shallow', null, InputOption::VALUE_NONE, 'Generate a shallow resource controller.'],
            ['nested', null, InputOption::VALUE_NONE, 'Generate a nested resource controller.'],
            ['prefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for the routes used.'],
            ['routeprefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for the routes used.'],
        ];
    }
}
