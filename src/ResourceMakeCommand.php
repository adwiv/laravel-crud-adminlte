<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ResourceMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'crud:resource';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new resource';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Resource';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->collection()) {
            $this->type = 'Resource collection';
        }

        parent::handle();
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->collection()
            ? $this->resolveStubPath('/stubs/resource/collection.stub')
            : $this->resolveStubPath('/stubs/resource/resource.stub');
    }

    /**
     * Determine if the command is generating a resource collection.
     *
     * @return bool
     */
    protected function collection()
    {
        return $this->option('collection') ||
            Str::endsWith($this->argument('name'), 'Collection');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param string $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__ . $stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Http\Resources';
    }

    protected function buildClass($name)
    {
        if (!($model = $this->option('model'))) {
            $suffix = $this->type;
            $baseLen = strlen($suffix);
            $baseName = class_basename($name);
            if (strlen($baseName) > $baseLen && str_ends_with($baseName, $suffix)) {
                $model = substr($baseName, 0, -$baseLen);
            } else {
                $this->error('Could not guess model name. Please use --model option');
            }
        }

        $modelClass = $this->parseModel($model);
        /** @var Model $modelObject */
        $modelObject = new $modelClass();
        $fields = $modelObject->getVisible();
        $hidden = $modelObject->getHidden();
        if (empty($fields)) {
            $table = $modelObject->getTable();
            $columns = ColumnInfo::fromTable($table);
            $fields = array_keys($columns);
        }
        $FIELDS = "";

        foreach ($fields as $field) {
            if (!in_array($field, $hidden)) {
                $FIELDS .= "            '$field' => \$this->$field,\n";
            }
        }

        $replace = [
            '{{ namespacedModel }}' => $modelClass,
            '{{ model }}' => class_basename($modelClass),
            '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
            '{{ pluralModelVariable }}' => Str::plural(lcfirst(class_basename($modelClass))),
            '{{ FIELDS }}' => trim($FIELDS),
        ];

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    /**
     * Get the fully-qualified model class name.
     *
     * @param string $model
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function parseModel($model)
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new \InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['collection', 'c', InputOption::VALUE_NONE, 'Create a resource collection'],
            ['force', null, InputOption::VALUE_NONE, 'Overwrite if file exists.'],
            ['model', null, InputOption::VALUE_REQUIRED, 'Model to use for getting attributes.'],
        ];
    }
}
