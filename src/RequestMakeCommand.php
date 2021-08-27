<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class RequestMakeCommand extends GeneratorCommand
{
    use ClassHelper;

    protected $name = 'crud:request';
    protected $description = 'Create a new form request class';
    protected $type = 'Request';

    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/request.stub');
    }

    protected function buildClass($name)
    {
        $model = $this->guessModelName($name);
        $modelClass = $this->fullModelClass($model);

        /** @var Model $modelObject */
        $modelObject = new $modelClass();
        $table = $modelObject->getTable();
        $columns = ColumnInfo::fromTable($table);

        $fillable = [];
        $RULES = "";
        $MESSAGES = "";
        foreach (array_keys($columns) as $field) {
            $ignore = ['id', 'uid', 'uuid', 'remember_token', 'created_at', 'updated_at', 'deleted_at'];
            if (in_array($field, $ignore)) continue;

            if ($modelObject->isFillable($field)) {
                $fillable[] = $field;
                /** @var ColumnInfo $column */
                $column = $columns[$field];
                $required = $column->notNull ? 'required' : 'nullable';
                if ($column->castType() == 'datetime') {
                    $RULES .= "            '$field' => \"$required|array|\",\n";
                    $RULES .= "            '$field.date' => \"$required|date_format:Y-m-d|\",\n";
                    $RULES .= "            '$field.time' => \"$required:$field.date|date_format:H:i:s|\",\n";
                    $MESSAGES .= "            //'$field' => '',\n";
                } else {
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
