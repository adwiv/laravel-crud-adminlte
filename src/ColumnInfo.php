<?php

/** @noinspection PhpMultipleClassesDeclarationsInOneFile */

namespace Adwiv\Laravel\CrudGenerator;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ColumnInfo
{
    public string $name;
    public string $type;
    public int $length = 0;
    public int $precision = 0;
    public bool $unsigned;
    public bool $notNull;
    public bool $unique;
    public ?string $foreign;
    public array $values;

    public function __construct(array $column, array $uniques, array $foreign)
    {
        // echo "Column: " . print_r($column, true) . "\n";
        $this->name = $column['name'];
        $this->type = $column['type_name'];
        $this->extractFieldLength($column['type']);
        $this->unsigned = str_contains($column['type'], 'unsigned');
        $this->notNull = $column['nullable'] === false;
        $this->unique = in_array($this->name, $uniques);
        $this->foreign = $foreign[$this->name] ?? null;

        if ($this->type == 'enum' || $this->type == 'set') {
            $this->values = self::getEnumValues($this->type, $column['type']);
        }
    }

    public function isUuid(): bool
    {
        return $this->type == 'char' && $this->length == 36;
    }

    public function isUlid(): bool
    {
        return $this->type == 'char' && $this->length == 26;
    }

    private function extractFieldLength($type): void
    {
        // Use preg_match to extract the length within parentheses
        if (preg_match('/\((\d+)\)/', $type, $matches)) {
            $this->length = (int)$matches[1];
        }

        if (preg_match('/\((\d+),(\d+)\)/', $type, $matches)) {
            $this->length = (int)$matches[1];
            $this->precision = (int)$matches[2];
        }
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
            case 'smallint':
                return 'integer';
            case 'decimal':
            case 'float':
                return 'numeric';
            case 'varchar':
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
                return 'date_format:Y-m-d';
            case 'timestamp':
            case 'datetime':
            case 'datetime_immutable':
                return 'date_format:Y-m-d\\TH:i:s';
            case 'time':
            case 'time_immutable':
                return 'date_format:H:i:s';
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
            case 'tinyint':
                return $this->length === 1 ? 'boolean' : 'integer';
            case 'decimal':
                return "decimal:{$this->precision}";
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

        $uniques = self::getUniqueColumns($table);
        $foreign = self::getForeignColumns($table);

        $columns = [];
        foreach (Schema::getColumns($table) as $column) {
            $columnName = $column['name'];
            $columnInfo = new ColumnInfo($column, $uniques, $foreign);
            $columns[$columnName] = $columnInfo;
        }
        return $columns;
    }

    public static function getUniqueColumns($table): array
    {
        $uniques = [];
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] && count($index['columns']) == 1) {
                $uniques[] = $index['columns'][0];
            }
        }
        return $uniques;
    }

    public static function getForeignColumns($table): array
    {
        $foreign = [];
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (count($foreignKey['columns']) == 1) {
                $localColumn = $foreignKey['columns'][0];
                $foreignTable = $foreignKey['foreign_table'];
                $foreignColumn = $foreignKey['foreign_columns'][0];
                $foreign[$localColumn] = "$foreignTable,$foreignColumn";
            }
        }
        return $foreign;
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
