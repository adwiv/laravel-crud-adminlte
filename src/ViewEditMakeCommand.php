<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Support\Str;

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

        $datePlugin = false;
        $dateConfig = "";
        $dateTimeConfig = "";

        /**
         * @var string $field
         * @var ColumnInfo $columnInfo
         */
        foreach ($fields as $field => $columnInfo) {
            $castType = $columnInfo->castType();
            $valType = $columnInfo->validationType();
            $fieldName = ucwords(str_replace('_', ' ', Str::snake($field)));
            $required = $columnInfo->notNull ? 'required' : '';
            // echo "$field:: {$columnInfo->type} {$columnInfo->castType()} {$columnInfo->type} {$columnInfo->length}\n";
            if ($castType == 'boolean') {
                $emptyOptionClass = $required ? 'class="d-none"' : '';
                $FIELDS .=
                    "                    <x-adminlte-select name=\"$field\" label=\"$fieldName\" class=\"custom-select\" disable-feedback $required\n" .
                    "                                       label-class=\"text-sm\" fgroup-class=\"col-sm-6 col-lg-4 col-xl-3\">\n".
                    "                        <option value=\"\" $emptyOptionClass>Select $field...</option>\n".
                    "                        <option value=\"0\" @if(intval(old('$field') ?? \${$modelVariable}->{$field} ?? '-1') === 0) selected @endif>FALSE</option>\n".
                    "                        <option value=\"1\" @if(intval(old('$field') ?? \${$modelVariable}->{$field} ?? '-1') === 1) selected @endif>TRUE</option>\n".
                    "                    </x-adminlte-select>\n";
            } else if ($columnInfo->type == 'enum' || $columnInfo->type == 'set') {
                $emptyOptionClass = $required ? 'class="d-none"' : '';
                $multiple = $columnInfo->castType() == 'set' ? 'multiple' : '';
                $FIELDS .=
                    "                    <x-adminlte-select name=\"$field\" label=\"$fieldName\" class=\"custom-select\" disable-feedback $required $multiple\n" .
                    "                                       label-class=\"text-sm\" fgroup-class=\"col-sm-6 col-lg-4 col-xl-3\">\n".
                    "                        <option value=\"\" $emptyOptionClass>Select $field...</option>\n".
                    "                        @foreach(['option1'=>'Option1', 'option2'=>'Option2'] as \$key => \$value)\n".
                    "                            <option value=\"{{\$key}}\" @if((old('$field') ?? \${$modelVariable}->{$field} ?? '') == \$key) selected @endif>{{\$value}}</option>\n".
                    "                        @endforeach\n".
                    "                    </x-adminlte-select>\n";
            } else if ($foreignKey = $columnInfo->foreign) {
                $emptyOptionClass = $required ? 'class="d-none"' : '';
                list($foreignTable, $foreignField) = preg_split('/,/', $foreignKey);
                $foreignVar = Str::singular($foreignTable);
                $foreignClass = Str::studly($foreignVar);
                $foreignClass = "App\\Models\\$foreignClass";
                $FIELDS .=
                    "                    <x-adminlte-select name=\"$field\" label=\"$fieldName\" class=\"custom-select\" disable-feedback $required\n" .
                    "                                       label-class=\"text-sm\" fgroup-class=\"col-sm-6 col-lg-4 col-xl-3\">\n".
                    "                        <option value=\"\" $emptyOptionClass>Select $foreignVar...</option>\n".
                    "                        @foreach($foreignClass::all() as \$fk_$foreignVar)\n".
                    "                            <option value=\"{{\$fk_{$foreignVar}->id}}\" @if((old('$field') ?? \${$modelVariable}->{$foreignField} ?? '') == \$fk_{$foreignVar}->id) selected @endif>{{\$fk_{$foreignVar}->name}}</option>\n".
                    "                        @endforeach\n".
                    "                    </x-adminlte-select>\n";
            } else if ($castType == 'datetime') {
                $FIELDS .=
                    "                    <x-adminlte-input type=\"date\" name=\"{$field}[date]\" label=\"$fieldName\" disable-feedback $required\n" .
                    "                                      value=\"{{old('$field')['date'] ?? substr(\$$modelVariable->$field ?? '', 0, 10)}}\"\n" .
                    "                                      label-class=\"text-sm\" fgroup-class=\"col-12 col-lg-8 col-xl-3\">\n" .
                    "                        <x-slot name=\"appendSlot\">\n".
                    "                            <input type=\"time\" step=\"1\" name=\"{$field}[time]\" class=\"form-control\" $required\n".
                    "                                   title=\"time\" value=\"{{old('$field')['time'] ?? substr(\$$modelVariable->$field ?? '', 11, 8)}}\" />\n".
                    "                        </x-slot>\n".
                    "                    </x-adminlte-input>\n";
            } else if ($valType == 'string' && $columnInfo->length > 255) {
                $FIELDS .=
                    "                    <x-adminlte-textarea name=\"$field\" label=\"$fieldName\" rows=\"5\" disable-feedback $required\n" .
                    "                                         fgroup-class=\"col-12\"\n" .
                    "                                         label-class=\"text-sm\">{{ old('$field', \$$modelVariable->$field ?? '') }}</x-adminlte-textarea>\n";
            } else {
                $type = 'type="text"';
                if($valType == 'date') $type = 'type="date"';
                if($valType == 'integer') $type = 'type="number"';
                if($valType == 'numeric') $type = 'type="number" step="0.01"';

                $min = $columnInfo->unsigned ? 'min="0"' : '';

                $FIELDS .=
                    "                    <x-adminlte-input $type $min name=\"$field\" label=\"$fieldName\" disable-feedback $required\n" .
                    "                                      value=\"{{ old('$field', \$$modelVariable->$field ?? '') }}\"\n" .
                    "                                      label-class=\"text-sm\" fgroup-class=\"col-sm-6 col-lg-4 col-xl-3\">\n" .
                    "                    </x-adminlte-input>\n";
            }
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
