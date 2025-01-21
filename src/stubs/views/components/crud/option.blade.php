@props([
    'name', 'value', 'label',
    'model' => null, 'modelKey' => null, 'oldKey' => null, 'selected' => null,
])
@php
    if($model && isset($selected)) throw new \Exception('Selected and model cannot be set at the same time');
    if($model && !is_object($model) && !is_array($model)) throw new \Exception('Model must be an object or an array');

    $oldKey ??= preg_replace('@\[([^]]+)\]@', '.$1', preg_replace('@\[\]$@', '', $name));

    if(!isset($selected)) {
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
            $selected = in_array("$value", $selectedValue);
        } else if($selectedValue) {
            $selected = "$selectedValue" === "$value";
        }
    }
@endphp

<option value="{{ $value }}" {{ $selected ? 'selected' : '' }} {{ $attributes }}>
    {{ $label }}
</option>