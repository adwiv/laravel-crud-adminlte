<?php
/** @noinspection PhpMultipleClassesDeclarationsInOneFile */

namespace Adwiv\Laravel\CrudGenerator;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Illuminate\Support\Facades\DB;

class ColumnInfo
{
    private static $inited;
    private static $schema;
    private static $databasePlatform;

    public $name;
    public $type;
    public $length;
    public $unsigned;
    public $notNull;
    public $unique;
    public $foreign;

    public static function init()
    {
        self::$inited = true;
        Type::addType('set', SetType::class);
        Type::addType('enum', EnumType::class);

        self::$schema = DB::getDoctrineSchemaManager();
        self::$databasePlatform = self::$schema->getDatabasePlatform();
        self::$databasePlatform->registerDoctrineTypeMapping('set', 'set');
        self::$databasePlatform->registerDoctrineTypeMapping('enum', 'enum');
    }

    public function __construct(Column $column, array $uniques, array $foreign)
    {
        $this->name = $column->getName();
        $this->type = $column->getType()->getName();
        $this->length = $column->getLength() ?? 0;
        $this->unsigned = $column->getUnsigned();
        $this->notNull = $column->getNotnull();
        $this->unique = in_array($this->name, $uniques);
        $this->foreign = $foreign[$this->name] ?? null;
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
        if (!self::$inited) self::init();
        assert(self::$schema->tablesExist($table), "Database table '$table' does not exist.");

        $columns = [];
        $uniques = [];
        $foreign = [];
        $tableInfo = self::$schema->listTableDetails($table);

        foreach ($tableInfo->getIndexes() as $index) {
            if ($index->isUnique() && count($index->getColumns()) == 1) {
                $uniques[] = $index->getColumns()[0];
            }
        }
        foreach ($tableInfo->getForeignKeys() as $foreignKey) {
            if (count($foreignKey->getColumns()) == 1) {
                $localColumn = $foreignKey->getLocalColumns()[0];
                $foreignTable = $foreignKey->getForeignTableName();
                $foreignColumn = $foreignKey->getForeignColumns()[0];
                $foreign[$localColumn] = "$foreignTable,$foreignColumn";
            }
        }
        foreach ($tableInfo->getColumns() as $column) {
            $columns[$column->getName()] = new ColumnInfo($column, $uniques, $foreign);
        }
        return $columns;
    }

    public static function getReferencingKeys($refTable)
    {
        if (!self::$inited) self::init();
        $keys = [];
        foreach (self::$schema->listTables() as $table) {
            $columns = null;
            foreach ($table->getForeignKeys() as $foreignKey) {
                if (!$columns) $columns = self::fromTable($table->getName());
                if ($foreignKey->getForeignTableName() == $refTable) {
                    if (count($foreignKey->getColumns()) == 1) {
                        $key = [
                            'table' => $foreignKey->getLocalTableName(),
                            'key' => $foreignKey->getLocalColumns()[0],
                            'ref' => $foreignKey->getForeignColumns()[0],
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

class EnumType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'enum';
    }

    public function getName(): string
    {
        return 'enum';
    }
}

class SetType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'set';
    }

    public function getName(): string
    {
        return 'set';
    }
}

