<?php

namespace Adwiv\Laravel\CrudGenerator\Console;

use Adwiv\Laravel\CrudGenerator\ColumnInfo;
use Adwiv\Laravel\CrudGenerator\CrudHelper;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class EnumMakeCommand extends GeneratorCommand
{
    use CrudHelper;

    protected $name = 'crud:enum';
    protected $description = 'Create a new enum class';
    protected $type = 'Enum';

    protected function getStub(): string
    {
        return $this->resolveStubPath("/stubs/enums/enum.stub");
    }

    protected function buildClass($name)
    {
        $enum = $this->argument('name');
        $modelFullName = $this->qualifyClass($enum);
        $modelBaseName = class_basename($modelFullName);

        $table = $this->getCrudTable($this->option('model') ?? '');
        $column = $this->getCrudEnumColumn($table);

        $columns = ColumnInfo::fromTable($table);
        $columnInfo = $columns[$column];
        if (!$columnInfo) $this->fail("Column $column not found in table $table.");
        if ($columnInfo->type != 'enum' && $columnInfo->type != 'set') $this->fail("Column $column is not an enum or set.");
        if (empty($columnInfo->values)) $this->fail("Column $column has no values.");

        $ENUMS = "";
        $LABELS = "";
        foreach ($columnInfo->values as $value) {
            $varName = Str::camel($value);
            $ucValue = Str::title(Str::snake($varName, ' '));
            $ENUMS .= "    case $varName = '$value';\n";
            $LABELS .= "            self::$varName => '$ucValue',\n";
        }

        $replace = [
            '{{ ENUMS }}' => trim($ENUMS),
            '{{ LABELS }}' => trim($LABELS),
        ];

        $replace = $this->buildModelReplacements($replace, $modelFullName, $modelBaseName);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    /**
     * Build the model replacement values.
     */
    protected function buildModelReplacements(array $replace, string $modelFullName, string $modelBaseName): array
    {
        return array_merge($replace, [
            '{{ namespacedModel }}' => $modelFullName,
            '{{ model }}' => $modelBaseName,
        ]);
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the enum class.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite if file exists.'],
            ['skip', 's', InputOption::VALUE_NONE, 'Skip if file exists.'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The name of the model class.'],
            ['table', 't', InputOption::VALUE_REQUIRED, 'The name of the table from which the enum values are taken.'],
            ['column', 'c', InputOption::VALUE_REQUIRED, 'The name of the column from which the enum values are taken.'],
        ];
    }
}
