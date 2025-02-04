<?php

namespace Adwiv\Laravel\CrudGenerator\Console;

use Adwiv\Laravel\CrudGenerator\CrudHelper;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

class ResourceMakeCommand extends GeneratorCommand
{
    use CrudHelper;

    protected $name = 'crud:resource';
    protected $description = 'Create a new resource';
    protected $type = 'Resource';

    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/resource/resource.stub');
    }

    protected function buildClass($name)
    {
        // Deduce the model name
        $modelFullName = $this->getCrudModel($name);
        $modelBaseName = class_basename($modelFullName);
        $modelInstance = new $modelFullName();
        $modelCasts = $modelInstance->getCasts();

        $ignoreFields = ['password', 'remember_token', 'created_at', 'updated_at', 'deleted_at'];
        $fields = $this->getVisibleFields($modelFullName, $ignoreFields);

        $FIELDS = "";
        foreach ($fields as $field => $columnInfo) {
            // Add boolean indicator for nullable datetime fields
            if ($columnInfo->type === 'timestamp' && $columnInfo->isNullable() && Str::endsWith($field, '_at')) {
                $camelName = Str::camel(substr($field, 0, -3));
                $FIELDS .= "            '$camelName' => \$this->$field != null,\n";
            }

            $camelName = Str::camel($field);
            $castType = $modelCasts[$field] ?? null;
            if ($castType) $castType = explode(':', $castType)[0];

            if ($castType === 'date' || $castType === 'immutable_date') {
                if ($columnInfo->isNullable())
                    $FIELDS .= "            '$camelName' => \$this->{$field}?->format('Y-m-d'),\n";
                else
                    $FIELDS .= "            '$camelName' => \$this->{$field}->format('Y-m-d'),\n";
            } else {
                $FIELDS .= "            '$camelName' => \$this->$field,\n";
            }
        }

        $replace = [
            '{{ namespacedModel }}' => $modelFullName,
            '{{ model }}' => $modelBaseName,
            '{{ FIELDS }}' => trim($FIELDS),
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
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite if file exists.'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Specify Model to use.'],
        ];
    }
}
