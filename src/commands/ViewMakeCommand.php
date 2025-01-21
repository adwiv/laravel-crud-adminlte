<?php

namespace Adwiv\Laravel\CrudGenerator\Commands;

use Adwiv\Laravel\CrudGenerator\CrudHelper;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ViewMakeCommand extends GeneratorCommand
{
    use CrudHelper;

    protected $name = 'crud:view';
    protected $type = 'View';
    protected $view = 'view';

    protected $viewName = null;
    protected $viewType = null;
    protected $resourceType = null;

    protected function getStub(): string
    {
        $nested = $this->resourceType !== 'regular' ? '.nested' : '';
        return $this->resolveStubPath("/stubs/views/{$this->viewType}{$nested}.stub");
    }

    protected function getPath($name)
    {
        $path = str_replace('.', DIRECTORY_SEPARATOR, $this->getNameInput());
        return $this->viewPath("$path.blade.php");
    }

    protected final function buildClass($name)
    {
        $this->viewName = $this->getNameInput();
        $segments = explode('.', $this->viewName);

        // Validate the view name
        if (count($segments) < 2) $this->fail("Invalid view name. Must be in the format of: [<prefix>.]<model>.<view>");

        // Validate the view type
        $allowedViewTypes = ['index', 'edit', 'show'];
        $this->viewType = array_pop($segments);
        if (!in_array($this->viewType, $allowedViewTypes)) $this->fail("Invalid view name. Must end in one of: index, edit, show");

        $modelViewPrefix = array_pop($segments);
        $guessModel = Str::studly(Str::singular($modelViewPrefix));
        $modelFullName = $this->getCrudModel($guessModel);

        $viewPrefix = substr($this->viewName, 0, strrpos($this->viewName, '.'));
        return $this->buildView($viewPrefix, $modelFullName);
    }

    private function buildView($viewPrefix, $modelFullName)
    {
        $modelBaseName = class_basename($modelFullName);

        // Get the resource type
        $this->resourceType = $this->getCrudNestedType($modelFullName);

        // Check if the model has a parent model
        $parentBaseName = $parentFullName = null;
        if ($this->resourceType !== 'regular') {
            $parentFullName = $this->getCrudParentModel($modelFullName) or $this->fail("No parent model even though resource type is $this->resourceType");
            $parentBaseName = class_basename($parentFullName);
        }

        // Get the route prefix
        $routePrefix = $this->getCrudRoutePrefix($modelBaseName, $parentBaseName, $viewPrefix);
        $routePrefixParts = explode('.', $routePrefix);
        $modelRoutePrefix = array_pop($routePrefixParts);
        $parentRoutePrefix = array_pop($routePrefixParts);

        $this->copyBladeFiles();

        $ignore = ['id', 'uid', 'uuid', 'remember_token', 'created_at', 'updated_at', 'deleted_at'];
        $fields = $this->getVisibleFields($modelFullName, $ignore);
        $replace = $this->buildViewReplacements($modelFullName, $fields);

        $replace = array_merge(
            $replace,
            [
                '{{ homeroute }}' => 'home', //TODO:
                '{{ routeprefix }}' => $routePrefix,
                '{{ parentrouteprefix }}' => $parentRoutePrefix,
                '{{ model }}' => $modelBaseName,
                '{{ modelVariable }}' => Str::singular($modelRoutePrefix),
                '{{ pluralModelTitle }}' => Str::title(Str::snake(Str::plural($modelBaseName), ' ')),
                '{{ pluralModelVariable }}' => $modelRoutePrefix,
                '{{ parentModel }}' => $parentBaseName ?? '',
                '{{ parentModelVariable }}' => $parentRoutePrefix ? Str::singular($parentRoutePrefix) : '',
                '{{ pluralParentModelTitle }}' => $parentBaseName ? Str::title(Str::snake(Str::plural($parentBaseName), ' ')) : '',
                '{{ pluralParentModelVariable }}' => $parentRoutePrefix ? $parentRoutePrefix : '',
            ],
        );

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($modelFullName)
        );
    }

    protected function buildViewReplacements($modelClass, $fields): array
    {
        if ($this->viewType == 'index') return $this->buildIndexViewReplacements($modelClass, $fields);
        if ($this->viewType == 'edit') return $this->buildEditViewReplacements($modelClass, $fields);
        if ($this->viewType == 'show') return $this->buildShowViewReplacements($modelClass, $fields);
        $this->fail("Unknown view type '$this->viewType'.");
    }

    /**
     * Copy the script stubs to view directory
     */
    protected function copyBladeFiles()
    {
        $src = __DIR__ . '/../stubs/views/components';
        $dest = $this->laravel->resourcePath('views/components');
        // copy all files from stubs/views/components/layouts/ to resource/views/components/layouts/
        $this->copyDir("$src/crud/", "$dest/crud/");
        $this->copyDir("$src/layouts/", "$dest/layouts/");
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the view to create.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite if file exists.'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Use the specified model class.'],
            ['parent', 'p', InputOption::VALUE_REQUIRED, 'Use the specified parent class.'],
            ['shallow', null, InputOption::VALUE_NONE, 'Generate a shallow resource controller.'],
            ['prefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for views and routes.'],
            ['viewprefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for the views used.'],
            ['routeprefix', null, InputOption::VALUE_REQUIRED, 'Prefix path for the routes used.'],
        ];
    }

    protected function buildIndexViewReplacements($modelClass, $fields): array
    {
        $count = 0;
        $HEAD = $BODY = "";
        $modelVariable = lcfirst(class_basename($modelClass));
        foreach ($fields as $field => $columnInfo) {
            if (in_array($field, ['id', 'uid', 'uuid', 'remember_token', 'created_at', 'updated_at', 'deleted_at'])) continue;

            $fieldName = ucwords(str_replace('_', ' ', Str::snake($field)));
            $HEAD .= "                    <th class=\"\">$fieldName</th>\n";
            $BODY .= "                    <td class=\"\">{{ \$$modelVariable->$field }}</td>\n";
            $count++;
        }
        $EMPTY = "                        <td colspan=\"$count\" class=\"text-center\">No records found</td>";

        return [
            '{{ HEAD }}' => trim($HEAD),
            '{{ BODY }}' => trim($BODY),
            '{{ EMPTY }}' => trim($EMPTY),
        ];
    }

    protected function buildShowViewReplacements($modelClass, $fields): array
    {
        $FIELDS = "";
        $modelVariable = lcfirst(class_basename($modelClass));
        foreach ($fields as $field => $columnInfo) {
            $fieldName = ucwords(str_replace('_', ' ', Str::snake($field)));
            $FIELDS .= "
                <tr>
                    <td>$fieldName</td>
                    <td>{{ \$$modelVariable->$field }}</td>
                </tr>";
        }

        return ['{{ FIELDS }}' => trim($FIELDS)];
    }

    protected function buildEditViewReplacements($modelClass, $fields): array
    {
        $FIELDS = "";
        $modelVariable = lcfirst(class_basename($modelClass));

        /**
         * @var string $field
         * @var ColumnInfo $columnInfo
         */
        foreach ($fields as $field => $columnInfo) {
            $castType = $columnInfo->castType();
            $formInputType = $columnInfo->formInputType();
            $fieldName = ucwords(str_replace('_', ' ', Str::snake($field)));
            $required = $columnInfo->notNull ? 'required' : '';

            if ($castType == 'boolean') {
                $FIELDS .= <<<END
                <div class="col-sm-6 col-lg-4">
                    <x-crud.select name="$field" $required :model="\$$modelVariable" :options="['FALSE','TRUE']" disable-feedback/>
                </div>
END;
            } else if ($columnInfo->type == 'enum' || $columnInfo->type == 'set') {
                $multiple = $columnInfo->type == 'set' ? 'multiple' : '';
                $options = [];
                foreach ($columnInfo->values as $value) {
                    $options[] = "'$value'=>'$value'";
                }
                $options = implode(",", $options);
                //TODO: Handle set type - field should be array and selected values can be multiple
                $FIELDS .= <<<END
                <div class="col-sm-6 col-lg-4">
                    <x-crud.enum name="$field" $required $multiple :model="\$$modelVariable" :options="[$options]"/>
                </div>
END;
                $FIELDS .=
                    "                    <x-adminlte-select name=\"$field\" label=\"$fieldName\" class=\"custom-select\" disable-feedback $required $multiple\n" .
                    "                                       label-class=\"text-sm\" fgroup-class=\"col-sm-6 col-lg-4 col-xl-3\">\n" .
                    "                        <option value=\"\" $emptyOptionClass>Select $field...</option>\n" .
                    "                        @foreach([$options] as \$key => \$value)\n" .
                    "                            <option value=\"{{\$key}}\" @if((old('$field') ?? \${$modelVariable}->{$field} ?? '') == \$key) selected @endif>{{\$value}}</option>\n" .
                    "                        @endforeach\n" .
                    "                    </x-adminlte-select>\n";
            } else if ($foreignKey = $columnInfo->foreign) {
                $emptyOptionClass = $required ? 'class="d-none"' : '';
                list($foreignTable, $foreignField) = preg_split('/,/', $foreignKey);
                $foreignVar = Str::singular($foreignTable);
                $foreignClass = Str::studly($foreignVar);
                $foreignClass = "App\\Models\\$foreignClass";
                $FIELDS .=
                    "                    <x-adminlte-select name=\"$field\" label=\"$fieldName\" class=\"custom-select\" disable-feedback $required\n" .
                    "                                       label-class=\"text-sm\" fgroup-class=\"col-sm-6 col-lg-4 col-xl-3\">\n" .
                    "                        <option value=\"\" $emptyOptionClass>Select $foreignVar...</option>\n" .
                    "                        @foreach($foreignClass::all() as \$fk_$foreignVar)\n" .
                    "                            <option value=\"{{\$fk_{$foreignVar}->id}}\" @if((old('$field') ?? \${$modelVariable}->{$foreignField} ?? '') == \$fk_{$foreignVar}->id) selected @endif>{{\$fk_{$foreignVar}->name}}</option>\n" .
                    "                        @endforeach\n" .
                    "                    </x-adminlte-select>\n";
            } else if ($formInputType == 'string' && $columnInfo->length > 255) {
                $FIELDS .= <<<END
                <div class="col-sm-6 col-lg-4">
                    <x-crud.textarea name="$field" rows="5" $required :model="\$$modelVariable" disable-feedback/>
                </div>
END;
            } else {
                $type = 'type="text"';
                if ($formInputType == 'date') $type = 'type="date"';
                if ($formInputType == 'time') $type = 'type="time"';
                if ($formInputType == 'datetime') $type = 'type="datetime-local" step="1"';
                if ($formInputType == 'integer') $type = 'type="number"';
                if ($formInputType == 'numeric') $type = 'type="number" step="0.01"';

                $min = $columnInfo->unsigned ? 'min="0"' : '';

                $FIELDS .= <<<END
                <div class="col-sm-6 col-lg-4">
                    <x-crud.input $type $min name="$field" $required :model="\$$modelVariable" disable-feedback/>
                </div>
END;
            }
        }

        return ['{{ FIELDS }}' => trim($FIELDS)];
    }
}
