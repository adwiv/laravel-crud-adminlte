<?php

namespace App\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Collection;
use UnitEnum;

use function Illuminate\Support\enum_value;

/**
 * Convert a CSV string for example from a mysql SET column to an array.
 * 
 * Using the class directly, we can cast to and from a string array.
 * 
 * You can use the of() method to specify the Enum for the cast.
 * 
 * Example:
 * 
 *     'roles' => CsvArray::class,
 *     
 *     'roles' => CsvArray::of(Role::class),
 * 
 */
class CsvArray implements CastsAttributes, Castable
{
    public function get($model, string $key, $value, array $attributes)
    {
        return $value ? explode(',', $value) : [];
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }

    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @template TEnum of \UnitEnum|\BackedEnum
     *
     * @param  array{class-string<TEnum>}  $arguments
     * @return \Illuminate\Contracts\Database\Eloquent\CastsAttributes<\Illuminate\Support\Collection<array-key, TEnum>, iterable<TEnum>>
     */
    public static function castUsing(array $arguments)
    {
        if (empty($arguments)) return new static;

        return new class($arguments) implements CastsAttributes
        {
            protected $arguments;

            public function __construct(array $arguments)
            {
                $this->arguments = $arguments;
            }

            public function get($model, $key, $value, $attributes)
            {
                $enumClass = $this->arguments[0];
                if (!enum_exists($enumClass)) throw new \InvalidArgumentException($enumClass . ' must be a valid enum');

                $data = $value ? explode(',', $value) : [];

                return (new Collection($data))->map(function ($value) use ($enumClass) {
                    return is_subclass_of($enumClass, BackedEnum::class)
                        ? $enumClass::from($value)
                        : constant($enumClass . '::' . $value);
                })->toArray();
            }

            public function set($model, $key, $value, $attributes)
            {
                return $value !== null
                    ? implode(',', $this->serialize($model, $key, $value, $attributes))
                    : null;
            }

            public function serialize($model, string $key, $value, array $attributes)
            {
                return (new Collection($value))->map(function ($enum) {
                    return $this->getStorableEnumValue($enum);
                })->toArray();
            }

            protected function getStorableEnumValue($enum)
            {
                if (is_string($enum) || is_int($enum)) {
                    return $enum;
                }

                return enum_value($enum);
            }
        };
    }

    /**
     * Specify the Enum for the cast.
     *
     * @param  class-string  $class
     * @return string
     */
    public static function of($class)
    {
        return static::class . ':' . $class;
    }
}
