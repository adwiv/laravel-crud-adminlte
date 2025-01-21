@props([
    'name', 'value', 'type' => null, 'label' => null, 'id' => null,
    'model' => null, 'modelKey' => null, 'oldKey' => null, 'checked' => null,
    'outerClass' => null, 'labelClass' => null,
])
@php
    if($model && isset($checked)) throw new \Exception('Checked and model cannot be set at the same time');
    if($model && !is_object($model) && !is_array($model)) throw new \Exception('Model must be an object or an array');
    if(!in_array($type, ['checkbox', 'radio'])) throw new \Exception('Invalid checkbox/radio input type');

    $oldKey ??= preg_replace('@\[([^]]+)\]@', '.$1', preg_replace('@\[\]$@', '', $name));

    $id ??= $oldKey . '.' . Str::slug($value);
    $type ??= Str::endsWith($name, ']') ? 'checkbox' : 'radio';
    $label ??= Str::title(str_replace(['.', '_', '-'], ' ', $value));

    $outerClass = trim("form-check $outerClass");
    $labelClass = trim("form-check-label $labelClass");

    if(!isset($checked)) {
        $selectedValue = null;
        // Get value from model if available
        if($model) {
            $modelKey ??= $oldKey;
            if(is_object($model) && isset($model->{$modelKey})) {
                $selectedValue = $model->{$modelKey};
            } else if(is_array($model) && isset($model[$modelKey])) {
                $selectedValue = $model[$modelKey];
            }
        }

        // Set value from old input if available. 
        // 'none' is used to prevent the value from being set from old input.
        if($oldKey != 'none') $selectedValue = old($oldKey, $selectedValue);    

        if(is_array($selectedValue)) {
            $checked = in_array("$value", $selectedValue);
        } else if($selectedValue) {
            $checked = "$selectedValue" === "$value";
        }
    }
@endphp

<div class="{{ $outerClass }}">
    <label class="{{ $labelClass }}">
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" data-old-key="{{ $oldKey }}" {{ $checked ? 'checked' : '' }} {{ $attributess->merge(['class' => 'form-check-input']) }}>
        {{ $label }}
    </label>
</div>
