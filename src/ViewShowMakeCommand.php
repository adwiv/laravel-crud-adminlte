<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ViewShowMakeCommand extends ViewBaseMakeCommand
{
    protected $name = 'crud:view-show';
    protected $description = 'Create a detail view';
    protected $type = 'ShowView';
    protected $viewType = 'show';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/views/show.stub');
    }

    protected function buildViewReplacements($modelClass, $fields): array
    {
        $FIELDS = "";
        $modelVariable = lcfirst(class_basename($modelClass));
        foreach ($fields as $field) {
            $fieldName = ucwords(str_replace('_', ' ', Str::snake($field)));
            $FIELDS .= "                    <tr><td>$fieldName</td><td>{{ \$$modelVariable->$field }}</td></tr>\n";
        }

        return ['{{ FIELDS }}' => trim($FIELDS)];
    }
}
