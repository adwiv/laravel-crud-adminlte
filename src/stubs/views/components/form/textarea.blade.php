@props([
    'name', 'id' => null, 'label' => null, 'placeholder' => null,
    'igroupSize' => null, 'igroupClass' => null, 'fgroupClass' => null, 'flabelClass' => 'text-lightblue', 
    'appendIcon' => null, 'prependIcon' => null, 'appendSlot' => null, 'prependSlot' => null,
    'value' => null, 'model' => null, 'modelKey' => null, 'oldKey' => null,
])
@php
    if($value && $model) throw new \Exception('Value and model cannot be set at the same time');
    if($model && !is_object($model) && !is_array($model)) throw new \Exception('Model must be an object or an array');

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
    if($oldKey != 'none') {
        $value = old($oldKey, $value);
    }
@endphp

<x-crud.group id="{{ $id }}" label="{{ $label }}" 
        :igroupSize="$igroupSize" :igroupClass="$igroupClass" 
        :fgroupClass="$fgroupClass" :flabelClass="$flabelClass" 
        :appendIcon="$appendIcon" :prependIcon="$prependIcon"
        :appendSlot="$appendSlot" :prependSlot="$prependSlot">
    <textarea id="{{ $id }}" name="{{ $name }}" placeholder="{{ $placeholder }}" {{ $attributes->merge(['class' => $formControlClass]) }}>{{ $value }}</textarea>
</div>