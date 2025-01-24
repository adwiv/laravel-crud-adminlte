@props([
    'id', 'label', 'name', 
    'type' => null, 'placeholder' => null,
    'value' => null, 'model' => null, 'modelKey' => null, 'oldKey' => null,
    'enum' => null, 'options' => null, 'valueKey' => 'id', 'labelKey' => 'name', 'default' => null,  
])
@aware([
    'id', 'label', 'model' => null, 
])
@php
    if(!in_array($type, ['radio', 'checkbox', 'switch'])) throw new \Exception("Invalid choices type: $type");
    if($model && !is_object($model) && !is_array($model)) throw new \Exception('Model must be an object or an array');
    if($enum && $options) throw new \Exception('Enum and options cannot be used together');
    if($enum && !is_subclass_of($enum, UnitEnum::class)) throw new \Exception('Enum must be a subclass of UnitEnum');
    if($options && !is_array($options) && !($options instanceof \Illuminate\Support\Collection)) throw new \Exception('Options must be an array or a collection');
    
    if($options instanceof \Illuminate\Support\Collection) {
        $options = $options->pluck($labelKey, $valueKey)->toArray();
    }

    if($enum) {
        if($enum instanceof BackedEnum) {
            $options = array_map(fn($case) => [$case->value => $case->label() ?? Str::title(Str::kebab($case->value, ' '))], $enum::cases());
        } else {
            $options = array_map(fn($case) => [$case->name => Str::title(Str::kebab($case->name, ' '))], $enum::cases());
        }
    }

    $oldKey ??= preg_replace('@\[([^]]+)\]@', '.$1', preg_replace('@\[\]$@', '', $name));

    $value ??= $default;

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
    if($oldKey != 'none') {
        $value = old($oldKey, $value);    
    }

    $attributes = $attributes->merge(['class' => "custom-control custom-$type custom-control-inline col-form-label"]);
    if($oldKey != $name) $attributes = $attributes->merge(['data-old-key' => $oldKey]);
@endphp

<!-- {{ print_r($value, true) }} -->
@foreach($options as $key => $label)
    @php
        echo "<!-- $key -->";
        if(is_array($value)) {
            echo "<!-- Checking array: $key -->";
            $selected = in_array("$key", $value);
        } else {
            echo "<!-- Checking string: $key -->";
            $selected = "$value" === "$key"; // Compare as strings to avoid type mismatch
        }
        $choiceId = $id . '.' . Str::slug($key, '-');
    @endphp
    <div {{ $attributes }}>
        <input type="{{ $type === 'switch' ? 'checkbox' : $type }}" name="{{ $name }}" id="{{ $choiceId }}" 
            class="custom-control-input" value="{{ $key }}" @if($selected) checked="checked" @endif>
        <label class="custom-control-label form-check-label" for="{{ $choiceId }}">{{ $label }}</label>
    </div>      
@endforeach