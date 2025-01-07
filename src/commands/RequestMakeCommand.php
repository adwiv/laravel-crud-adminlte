<?php

namespace Adwiv\Laravel\CrudGenerator\Commands;

use Adwiv\Laravel\CrudGenerator\ClassHelper;
use Adwiv\Laravel\CrudGenerator\ColumnInfo;
use Adwiv\Laravel\CrudGenerator\CrudHelper;
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

        /** @var Model $modelObject */
        $modelObject = new $modelFullName();
        $table = $modelObject->getTable();
        $columns = ColumnInfo::fromTable($table);

        $fillable = [];
        $RULES = "";
        $MESSAGES = "";
        foreach (array_keys($columns) as $field) {
            $ignore = ['id', 'uid', 'uuid', 'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at'];
            if (in_array($field, $ignore)) continue;

            if ($modelObject->isFillable($field)) {
                $fillable[] = $field;

                /** @var ColumnInfo $column */
                $column = $columns[$field];
                if ($column->unique) $this->unique = true;
                $required = $column->notNull ? 'required' : 'nullable';

                $type = $column->validationType();
                $min = $column->unsigned ? '|min:0' : '';
                $max = $column->length > 0 ? '|max:' . $column->length : '';
                $exists = $column->foreign ? "|exists:$column->foreign" : '';
                $unique = $column->unique ? "|unique:$table,$field{\$ignoreId}" : '';

                $values = $column->type == 'enum' ? '|in:' . implode(',', $column->values) . '' : '';
                $RULES .= "            '$field' => \"$required|$type$min$max$unique$exists$values\",\n";
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
            ['prefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for the routes used.'],
            ['routeprefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for the routes used.'],
        ];
    }
}
