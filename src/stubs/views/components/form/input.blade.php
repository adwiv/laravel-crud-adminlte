@props([
    'name', 'id' => null, 'label' => null, 'placeholder' => null, 'type' => 'text',
    'value' => null, 'model' => null, 'modelKey' => null, 'oldKey' => null,
])
@php
    if($value && $model) throw new \Exception('Value and model cannot be set at the same time');
    if($model && !is_object($model) && !is_array($model)) throw new \Exception('Model must be an object or an array');
    if(!in_array($type, ['text', 'email', 'number', 'password', 'file', 'date', 'time', 'search', 'tel', 'url'])) throw new \Exception("Invalid input type: $type");

    $oldKey ??= preg_replace('@\[([^]]+)\]@', '.$1', preg_replace('@\[\]$@', '', $name));

    $id ??= $oldKey;
    $label ??= Str::title(str_replace(['.', '_', '-'], ' ', $oldKey));
    $placeholder ??= 'Enter ' . $label;

    $formControlClass = "form-control" . ($igroupSize ? " form-control-$igroupSize" : '');

    // Get value from model if available
    if($model) {
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
@endphp

<x-crud.group id="{{ $id }}" label="{{ $label }}" {{ $attributes->only('igroupSize', 'igroupClass', 'fgroupClass', 'flabelClass', 'appendIcon', 'prependIcon') }}>
    @if($prependSlot)
        <x-slot name="prependSlot">
            {{ $prependSlot }}
        </x-slot>
    @endif
    @if($appendSlot)
        <x-slot name="appendSlot">
            {{ $appendSlot }}
        </x-slot>
    @endif
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}" data-old-key="{{ $oldKey }}" {{ $attributes->merge(['class' => $formControlClass]) }}>
</div>