<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ResourceMakeCommand extends GeneratorCommand
{
    use ClassHelper;

    protected $name = 'crud:resource';
    protected $description = 'Create a new resource';
    protected $type = 'Resource';

    public function handle()
    {
        if ($this->collection()) {
            $this->type = 'Collection';
        }

        parent::handle();
    }

    protected function getStub(): string
    {
        return $this->collection()
            ? $this->resolveStubPath('/stubs/resource/collection.stub')
            : $this->resolveStubPath('/stubs/resource/resource.stub');
    }

    protected function collection(): bool
    {
        return $this->option('collection') || Str::endsWith($this->argument('name'), 'Collection');
    }

    protected function buildClass($name)
    {
        $model = $this->guessModelName($name);
        $modelClass = $this->getModelClass($model);

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
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite if file exists.'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Specify Model to use.'],
            ['collection', 'c', InputOption::VALUE_NONE, 'Create a resource collection'],
        ];
    }
}
