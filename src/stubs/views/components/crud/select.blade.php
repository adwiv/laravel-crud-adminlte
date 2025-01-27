@props([
    'id', 'label', 'name', 
    'type' => 'standard', 'placeholder' => null, 
    'value' => null, 'model' => null, 'modelKey' => null, 'oldKey' => null, 
])
@aware([
    'id', 'label', 'model' => null,
])
@php
    if($model && !is_object($model) && !is_array($model)) throw new \Exception('Model must be an object or an array');
    if(!in_array($type, ['standard', 'custom'])) throw new \Exception("Invalid input type: $type");

    $oldKey ??= preg_replace('@\[([^]]+)\]@', '.$1', preg_replace('@\[\]$@', '', $name));

    $attributes = $attributes->merge(['class' => 'custom-select']);
    if($oldKey != $name) $attributes = $attributes->merge(['data-old-key' => $oldKey]);
@endphp

<select id="{{ $id }}" name="{{ $name }}" {{ $attributes }}>
    {{ $slot }}
</select>