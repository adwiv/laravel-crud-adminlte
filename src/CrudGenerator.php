<?php

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CrudGenerator extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'crud:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create complete CRUD code';

    public function handle()
    {
        echo "Handling Crud\n";
        $resourceName = Str::studly($this->getNameInput());
        if (!($table = $this->option('table'))) {
            $table = Str::snake(Str::plural($resourceName));
        }

        $columns = ColumnInfo::fromTable($table);
        foreach ($columns as $column) {
            echo json_encode($column) . PHP_EOL;
        }
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        return trim($this->argument('name'));
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the resource'],
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
            ['table', null, InputOption::VALUE_REQUIRED, 'Use specified table name instead of guessing.'],
        ];
    }
}
