<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ViewIndexMakeCommand extends ViewGeneratorCommand
{
    protected $view = 'index';
    protected $name = 'crud:view-index';
    protected $description = 'Create a new index view';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/views/index.stub');
    }

    protected function buildViewReplacements($modelClass, $fields): array
    {
        $count = 0;
        $HEAD = $BODY = "";
        $modelVariable = lcfirst(class_basename($modelClass));
        foreach ($fields as $field => $columnInfo) {
            $fieldName = ucwords(str_replace('_', ' ', Str::snake($field)));
            $HEAD .= "                    <th class=\"\">$fieldName</th>\n";
            $BODY .= "                        <td class=\"\">{{ \$$modelVariable->$field }}</td>\n";
            $count++;
        }
        $EMPTY = "                        <td colspan=\"$count\" class=\"text-center\">No records found</td>";

        return [
            '{{ HEAD }}' => trim($HEAD),
            '{{ BODY }}' => trim($BODY),
            '{{ EMPTY }}' => trim($EMPTY),
        ];
    }
}
