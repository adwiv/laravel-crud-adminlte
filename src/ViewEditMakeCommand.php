<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ViewEditMakeCommand extends ViewBaseMakeCommand
{
    protected $name = 'crud:view-edit';
    protected $description = 'Create a new create/edit view';
    protected $type = 'EditView';
    protected $viewType = 'edit';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/views/edit.stub');
    }

    protected function buildViewReplacements($modelClass, $fields): array
    {
        $FIELDS = "";
        $modelVariable = lcfirst(class_basename($modelClass));
        foreach ($fields as $field) {
            $fieldName = ucwords(str_replace('_', ' ', Str::snake($field)));
            $FIELDS .=
                "                    <x-adminlte-input name=\"$field\" label=\"$fieldName\" disable-feedback required\n" .
                "                                      value=\"{{ old('$field', \$$modelVariable->$field ?? '') }}\"\n" .
                "                                      label-class=\"text-sm\" fgroup-class=\"col-sm-6 col-lg-4 col-xl-3\">\n" .
                "                    </x-adminlte-input>\n";
        }

        return ['{{ FIELDS }}' => trim($FIELDS)];
    }

    /**
     * Copy the script stubs to view directory
     */
    protected function copyBladeScripts()
    {
        parent::copyBladeScripts();

        $dir = $this->laravel->resourcePath('views/scripts/');
        if (!file_exists($dir)) mkdir($dir);
        $files = ["form-slugify.blade.php", "form-validate.blade.php"];
        foreach ($files as $file) {
            if (!file_exists("$dir$file"))
                copy($this->resolveStubPath("/stubs/views/scripts/$file"), "$dir$file");
        }
    }
}
