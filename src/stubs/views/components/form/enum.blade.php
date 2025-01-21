@props([
    'name', 'id' => null, 'label' => null, 'type' => null,
    'igroupSize' => null, 'igroupClass' => null, 'fgroupClass' => null, 'flabelClass' => 'text-lightblue', 
    'appendIcon' => null, 'prependIcon' => null, 'appendSlot' => null, 'prependSlot' => null,
    'value' => null, 'model' => null, 'modelKey' => null, 'oldKey' => null,
    'options',
])
@php
    if($value && $model) throw new \Exception('Value and model cannot be set at the same time');
    if($model && !is_object($model) && !is_array($model)) throw new \Exception('Model must be an object or an array');
    if(!isset($options) || !is_array($options)) throw new \Exception('Options must be set and should be an array');

    $oldKey ??= preg_replace('@\[([^]]+)\]@', '.$1', preg_replace('@\[\]$@', '', $name));

    $id ??= $oldKey;
    $type ??= Str::endsWith($name, ']') ? 'checkbox' : 'radio';
    $label ??= Str::title(str_replace(['.', '_', '-'], ' ', $oldKey));

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
    if($oldKey != 'none') {
        $value = old($oldKey, $value);    
    }
@endphp

<x-crud.group id="{{ $id }}" label="{{ $label }}" 
        :igroupSize="$igroupSize" :igroupClass="$igroupClass" 
        :fgroupClass="$fgroupClass" :flabelClass="$flabelClass" 
        :appendIcon="$appendIcon" :prependIcon="$prependIcon"
        :appendSlot="$appendSlot" :prependSlot="$prependSlot">
        @foreach($options as $key => $label)
            <input type="{{ $type }}" name="{{ $name }}" value="{{ $key }}" {{ "$value" === "$key" ? 'checked' : '' }} {{ $attributes->merge(['class' => $formControlClass]) }}> {{ $label }}
        @endforeach
</div>