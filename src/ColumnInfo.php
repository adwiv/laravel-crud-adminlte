<?php

/** @noinspection PhpMultipleClassesDeclarationsInOneFile */

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ColumnInfo
{
    public $name;
    public $type;
    public $length;
    public $unsigned;
    public $notNull;
    public $unique;
    public $foreign;
    public $values;

    public function __construct(array $column, array $uniques, array $foreign)
    {
        $this->name = $column['name'];
        $this->type = $column['type_name'];
        $this->length = $this->extractFieldLength($column['type']);
        $this->unsigned = str_contains($column['type'], 'unsigned');
        $this->notNull = $column['nullable'] === false;
        $this->unique = in_array($this->name, $uniques);
        $this->foreign = $foreign[$this->name] ?? null;

        if ($this->type == 'enum' || $this->type == 'set') {
            $this->values = self::getEnumValues($this->type, $column['type']);
        }
    }

    private static function extractFieldLength($type): int
    {
        // Use preg_match to extract the length within parentheses
        if (preg_match('/\((\d+)\)/', $type, $matches)) {
            return (int)$matches[1];
        }

        // Return 0 if no length is found
        return 0;
    }

    private static function getEnumValues($type, $fullType): array
    {
        if (!preg_match("/^$type\((.*)\)$/", $fullType, $matches)) die("Invalid $type value");
        return str_getcsv($matches[1], ',', "'");
    }

    public function validationType()
    {
        switch ($this->type) {
            case 'smallint':
            case 'integer':
            case 'bigint':
                return 'integer';
            case 'decimal':
            case 'float':
                return 'numeric';
            case 'string':
            case 'ascii_string':
            case 'enum':
            case 'text':
            case 'guid':
                return 'string';
            case 'boolean':
                return 'boolean';
            case 'date':
            case 'date_immutable':
            case 'datetime':
            case 'datetime_immutable':
            case 'time':
            case 'time_immutable':
                return 'date';
            case 'array':
            case 'simple_array':
            case 'json':
                return 'array';
        }
        return $this->type;
    }

    public function castType()
    {
        switch ($this->type) {
            case 'boolean':
                return 'boolean';
            case 'date':
            case 'date_immutable':
                return 'date';
            case 'timestamp':
            case 'datetime':
            case 'datetime_immutable':
                return 'datetime';
            case 'json':
            case 'array':
            case 'simple_array':
                return 'array';
        }
        return null;
    }

    /**
     * @return ColumnInfo[]
     * @throws Exception
     */
    public static function fromTable($table): array
    {
        assert(Schema::hasTable($table), "Database table '$table' does not exist.");

        $columns = [];
        $uniques = [];
        $foreign = [];

        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] && count($index['columns']) == 1) {
                $uniques[] = $index['columns'][0];
            }
        }

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (count($foreignKey['columns']) == 1) {
                $localColumn = $foreignKey['columns'][0];
                $foreignTable = $foreignKey['foreign_table'];
                $foreignColumn = $foreignKey['foreign_columns'][0];
                $foreign[$localColumn] = "$foreignTable,$foreignColumn";
            }
        }

        foreach (Schema::getColumns($table) as $column) {
            $columnName = $column['name'];
            $columnInfo = new ColumnInfo($column, $uniques, $foreign);
            $columns[$columnName] = $columnInfo;
        }
        
        return $columns;
    }

    public static function getReferencingKeys($refTable)
    {
        $keys = [];
        foreach (Schema::getTables() as $table) {
            $columns = null;
            foreach (Schema::getForeignKeys($table['name']) as $foreignKey) {
                if (!$columns) $columns = self::fromTable($table['name']);
                if ($foreignKey['foreign_table'] == $refTable) {
                    if (count($foreignKey['columns']) == 1) {
                        $key = [
                            'table' => $table['name'],
                            'key' => $foreignKey['columns'][0],
                            'ref' => $foreignKey['foreign_columns'][0],
                        ];
                        $key['unique'] = $columns[$key['key']]->unique;
                        $keys[] = $key;
                    }
                }
            }
        }
        return $keys;
    }
}
