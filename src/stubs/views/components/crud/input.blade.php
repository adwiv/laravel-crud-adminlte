@props([
    'id', 'label', 'name',
    'type' => 'text', 'placeholder' => null, 
    'value' => null, 'model' => null, 'modelKey' => null, 'oldKey' => null,
])
@aware([
    'id', 'label', 'model' => null,
])
@php
    if($model && !is_object($model) && !is_array($model)) throw new \Exception('Model must be an object or an array');
    if(!in_array($type, ['text', 'email', 'number', 'password', 'file', 'date', 'time', 'datetime-local', 'search', 'tel', 'url', 'textarea'])) throw new \Exception("Invalid input type: $type");

    $oldKey ??= preg_replace('@\[([^]]+)\]@', '.$1', preg_replace('@\[\]$@', '', $name));

    // Get value from model if available
    if(!$value && $model) {
        $modelKey ??= $oldKey;
        if(is_object($model) && isset($model->{$modelKey})) {
            $value = $model->{$modelKey};
        } else if(is_array($model) && isset($model[$modelKey])) {
            $value = $model[$modelKey];
        }
    }

    // Set value from old input if available. 
    // 'none' is used to prevent the value from being set from old input.
    if($type !== 'password' && $type !== 'file' && $oldKey != 'none') {
        $value = old($oldKey, $value);    
    }

    if($value instanceof \Carbon\Carbon) {
        if($type === 'date') {
            $value = $value->format('Y-m-d');
        } else if($type === 'time') {
            $value = $value->format('H:i:s');
        } else if($type === 'datetime-local') {
            $value = $value->format('Y-m-d\TH:i:s');
        }
    }

    $attributes = $attributes->merge(['class' => 'form-control']);
    if($oldKey != $name) $attributes = $attributes->merge(['data-old-key' => $oldKey]);
@endphp

@if($type === 'textarea')
    <textarea id="{{ $id }}" name="{{ $name }}" placeholder="{{ $placeholder ?? "Enter $label" }}" {{ $attributes }}>{{ $value }}</textarea>
@else
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder ?? "Enter $label" }}" {{ $attributes }}/>
@endif