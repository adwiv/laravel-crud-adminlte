<?php

namespace Adwiv\Laravel\CrudGenerator\Commands;

use Adwiv\Laravel\CrudGenerator\CrudHelper;
use BackedEnum;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use UnitEnum;

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
        $modelFullName = $this->getCrudModel($guessModel . $this->type);

        $viewPrefix = substr($this->viewName, 0, strrpos($this->viewName, '.'));
        return $this->buildView($viewPrefix, $modelFullName);
    }

    private function buildView($viewPrefix, $modelFullName)
    {
        $modelBaseName = class_basename($modelFullName);

        // Get the resource type
        $this->resourceType = $this->getCrudControllerType($modelFullName);

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
        // copy all view components
        $src = __DIR__ . '/../stubs/views/components';
        $dest = $this->laravel->resourcePath('views/components');
        $this->copyDir("$src/crud/", "$dest/crud/");
        $this->copyDir("$src/layouts/", "$dest/layouts/");
        // copy all view scripts
        $src = __DIR__ . '/../stubs/views/js';
        $dest = $this->laravel->publicPath('js');
        $this->copyDir("$src/", "$dest/");
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
            ['quiet', 'q', InputOption::VALUE_NONE, 'Do not output info messages.'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Use the specified model class.'],
            ['parent', 'p', InputOption::VALUE_REQUIRED, 'Use the specified parent class.'],
            ['regular', null, InputOption::VALUE_NONE, 'Generate a regular controller.'],
            ['shallow', null, InputOption::VALUE_NONE, 'Generate a shallow resource controller.'],
            ['nested', null, InputOption::VALUE_NONE, 'Generate a nested resource controller.'],
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
        $modelInstance = new $modelClass();
        $castTypes = $modelInstance->getCasts();
        foreach ($fields as $field => $columnInfo) {
            if (in_array($field, ['id', 'uid', 'uuid', 'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at'])) continue;

            $castType = $castTypes[$field] ?? null;
            $fieldName = ucwords(Str::replace(['_', '-', '.'], ' ', $field));
            $fieldValue = "\$$modelVariable->$field";
            if ($castType) $castType = explode(':', $castType)[0];

            if ($castType == 'array' || $columnInfo->type == 'set' || $castType === 'boolean') {
                $fieldValue = "json_encode(\${$modelVariable}->{$field})";
            }

            if ($castType === 'date' || $castType === 'immutable_date') {
                if ($columnInfo->isNullable())
                    $fieldValue = "\${$modelVariable}->{$field}?->format('Y-m-d')";
                else
                    $fieldValue = "\${$modelVariable}->{$field}->format('Y-m-d')";
            }

            $HEAD .= "                    <th class=\"\">$fieldName</th>\n";
            $BODY .= "                    <td class=\"\">{{ $fieldValue }}</td>\n";
            $count++;

            // Add boolean indicator for nullable datetime fields
            if ($columnInfo->type === 'timestamp' && $columnInfo->isNullable() && Str::endsWith($field, '_at')) {
                $fieldName = substr($fieldName, 0, -3);
                $HEAD .= "                    <th class=\"\">$fieldName</th>\n";
                $BODY .= "                    <td class=\"\">{{ json_encode($fieldValue != null) }}</td>\n";
                $count++;
            }
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
        $modelInstance = new $modelClass();
        $castTypes = $modelInstance->getCasts();

        foreach ($fields as $field => $columnInfo) {
            if (in_array($field, ['password', 'remember_token'])) continue;

            $castType = $castTypes[$field] ?? null;
            $fieldName = ucwords(Str::replace(['_', '-', '.'], ' ', $field));
            $fieldValue = "\$$modelVariable->$field";
            if ($castType) $castType = explode(':', $castType)[0];

            if ($castType == 'array' || $columnInfo->type == 'set' || $castType === 'boolean') {
                $fieldValue = "json_encode(\${$modelVariable}->{$field})";
            }

            if ($castType === 'date' || $castType === 'immutable_date') {
                if ($columnInfo->isNullable())
                    $fieldValue = "\${$modelVariable}->{$field}?->format('Y-m-d')";
                else
                    $fieldValue = "\${$modelVariable}->{$field}->format('Y-m-d')";
            }

            $FIELDS .= "
                <tr>
                    <td>$fieldName</td>
                    <td>{{ $fieldValue }}</td>
                </tr>";

            // Add boolean indicator for nullable datetime fields
            if ($columnInfo->type === 'timestamp' && $columnInfo->isNullable() && Str::endsWith($field, '_at')) {
                $fieldName = substr($fieldName, 0, -3);
                $FIELDS .= "
                <tr>
                    <td>$fieldName</td>
                    <td>{{ json_encode($fieldValue != null) }}</td>
                </tr>";
            }
        }

        return ['{{ FIELDS }}' => trim($FIELDS)];
    }

    protected function buildEditViewReplacements($modelClass, $fields): array
    {
        $FIELDS = "";
        $modelVariable = lcfirst(class_basename($modelClass));
        $modelInstance = new $modelClass();
        $modelCasts = $modelInstance->getCasts();

        /**
         * @var string $field
         * @var ColumnInfo $columnInfo
         */
        foreach ($fields as $field => $columnInfo) {
            if (in_array($field, ['id', 'uid', 'uuid', 'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at'])) continue;

            $castType = $modelCasts[$field] ?? null;
            $formInputType = $columnInfo->formInputType();
            $fieldName = ucwords(Str::snake($field, ' '));
            $required = false;
            $columnInfo->notNull ? 'required' : '';

            if ($castType === 'boolean') {
                $FIELDS .= <<<END

                <x-crud.group id="$field" label="$fieldName" class="col-sm-6 col-lg-4">
                    <x-crud.select name="$field"{$required}>
                        <x-crud.options :options="['FALSE','TRUE']"/>
                    </x-crud.select>
                </x-crud.group>

END;
            } else if ($columnInfo->type == 'enum' || $columnInfo->type == 'set') {
                $type = $columnInfo->type == 'set' ? 'checkbox' : 'radio';
                $choiceName = $columnInfo->type == 'set' ? $field . "[]" : $field;
                $pluralFieldVar = Str::plural($field);

                if ($castType) {
                    // Cast type is either enum class name or a castable type
                    $enumClass = last(explode(':', $castType));
                    $isBackedEnum = is_subclass_of($enumClass, BackedEnum::class);
                    if (!$isBackedEnum) $this->fail("Enum class $enumClass is not a BackedEnum");

                    $FIELDS .= <<<END

                <x-crud.group id="$field" label="$fieldName" class="col-sm-6 col-lg-4">
                    <x-crud.choices type="$type" name="$choiceName"{$required} :enum="$enumClass::class"/>
                </x-crud.group>

END;
                } else {

                    $options = [];
                    foreach ($columnInfo->values as $value) {
                        $ucValue = ucwords(Str::snake($value, ' '));
                        $options[] = "'$value'=>'$ucValue'";
                    }
                    $options = implode(",", $options);

                    $FIELDS .= <<<END

                @php
                    \$$pluralFieldVar = [$options];
                @endphp
                <x-crud.group id="$field" label="$fieldName" class="col-sm-6 col-lg-4">
                    <x-crud.choices type="$type" name="$choiceName"{$required} :options="\$$pluralFieldVar"/>
                </x-crud.group>

END;
                }
            } else if ($foreignKey = $columnInfo->foreign) {
                list($foreignTable, $foreignField) = preg_split('/,/', $foreignKey);
                $foreignVar = Str::singular($foreignTable);
                $foreignClass = Str::studly($foreignVar);
                $foreignClass = "App\\Models\\$foreignClass";
                $FIELDS .= <<<END

                <x-crud.group id="$field" label="$fieldName" class="col-sm-6 col-lg-4">
                    <x-crud.select name="$field"{$required}>
                        <x-crud.options :options="$foreignClass::all()" valueKey="$foreignField" labelKey="name"/>
                    </x-crud.select>
                </x-crud.group>

END;
            } else if (
                $formInputType == 'textarea' ||
                ($formInputType == 'string' && $columnInfo->length > 255)
            ) {
                $FIELDS .= <<<END

                <x-crud.group id="$field" label="$fieldName" class="col-sm-6 col-lg-4">
                    <x-crud.textarea name="$field" rows="5"{$required}/>
                </x-crud.group>

END;
            } else {
                $type = 'type="text"';
                if ($formInputType == 'date') $type = 'type="date"';
                if ($formInputType == 'time') $type = 'type="time"';
                if ($formInputType == 'datetime') $type = 'type="datetime-local" step="1"';
                if ($formInputType == 'integer') $type = 'type="number"';
                if ($formInputType == 'numeric') $type = 'type="number" step="0.01"';

                $lcFieldVar = strtolower($field);
                if ($lcFieldVar == 'email' || Str::endsWith($lcFieldVar, '_email')) $type = 'type="email"';
                if (in_array($lcFieldVar, ['password', 'password_confirmation'])) $type = 'type="password"';
                if (in_array($lcFieldVar, ['phone', 'mobile']) || Str::endsWith($lcFieldVar, ['_phone', '_mobile'])) $type = 'type="tel"';
                if (in_array($lcFieldVar, ['url', 'link']) || Str::endsWith($lcFieldVar, ['_url', '_link'])) $type = 'type="url"';

                $FIELDS .= <<<END

                <x-crud.group id="$field" label="$fieldName" class="col-sm-6 col-lg-4">
                    <x-crud.input $type name="$field"{$required}/>
                </x-crud.group>

END;
            }
        }

        $FIELDS = trim($FIELDS);

        $FIELDS = <<<END
        <x-crud.model class="row" :model="\$$modelVariable">
            $FIELDS
        </x-crud.model>

END;
        return ['{{ FIELDS }}' => trim($FIELDS)];
    }
}
