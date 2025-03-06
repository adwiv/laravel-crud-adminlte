@props([
    'id',
    'label',
    'name',
    'type' => null,
    'placeholder' => null,
    'value' => null,
    'model' => null,
    'modelKey' => null,
    'oldKey' => null,
    'options' => null,
    'valueKey' => 'id',
    'labelKey' => 'name',
    'default' => null,
])
@aware(['id', 'label', 'model' => null])
@php
  if (!in_array($type, ['radio', 'checkbox', 'switch', 'select'])) {
      throw new \Exception("Invalid choices type: $type");
  }
  if ($model && !is_object($model) && !is_array($model)) {
      throw new \Exception('Model must be an object or an array');
  }
  if ($options && !is_array($options) && !($options instanceof \Illuminate\Support\Collection)) {
      throw new \Exception('Options must be an array or a collection');
  }

  $options = collect($options)
      ->mapWithKeys(function ($option, $key) use ($labelKey, $valueKey) {
          if ($option instanceof BackedEnum) {
              return [$option->value => method_exists($option, 'label') ? $option->label() : Str::title(Str::snake(Str::camel($option->value), ' '))];
          }
          if ($option instanceof UnitEnum) {
              return [$option->name => Str::title(Str::snake(Str::camel($option->name), ' '))];
          }
          if (is_object($option)) {
              return [$option->{$valueKey} => $option->{$labelKey}];
          }
          return ["$key" => "$option"];
      })
      ->toArray();

  $oldKey ??= preg_replace('@\[([^]]+)\]@', '.$1', preg_replace('@\[\]$@', '', $name));

  // Get value from model if available
  if (!$value && $model) {
      $modelKey ??= $oldKey;
      if (is_object($model) && isset($model->{$modelKey})) {
          $value = $model->{$modelKey};
      } elseif (is_array($model) && isset($model[$modelKey])) {
          $value = $model[$modelKey];
      }
  }

  // If value is not set, set it to default
  $value ??= $default;

  // Set value from old input if available.
  // 'none' is used to prevent the value from being set from old input.
  if ($oldKey != 'none') {
      $value = old($oldKey, $value);
  }

  // Convert value to an array to handle both single and multiple values
  $value = collect($value)
      ->map(function ($item) {
          if ($item instanceof BackedEnum) {
              return "{$item->value}";
          } elseif ($item instanceof UnitEnum) {
              return "{$item->name}";
          } elseif (is_bool($item)) {
              return $item ? '1' : '0';
          }
          return "$item";
      })
      ->toArray();

  $attributes = $attributes->merge(['class' => "custom-$type"]);
  if ($oldKey != $name) {
      $attributes = $attributes->merge(['data-old-key' => $oldKey]);
  }
@endphp

@if ($type === 'select')
  <select id="{{ $id }}" name="{{ $name }}" {{ $attributes }}>
    <option value="" @if ($attributes->has('required')) disabled="disabled" @endif @if (empty($value)) selected="selected" @endif>
      {{ $placeholder ?? "Select $label" }}</option>

    @foreach ($options as $key => $label)
      @php
        $selected = in_array("$key", $value);
      @endphp
      <option value="{{ $key }}" {{ $selected ? 'selected="selected"' : '' }}>{{ $label }}</option>
    @endforeach
  </select>
@else
  @foreach ($options as $key => $label)
    @php
      $selected = in_array("$key", $value);
      $choiceId = $id . '.' . Str::slug($key, '-');
    @endphp
    <div {{ $attributes->merge(['class' => 'custom-control custom-control-inline col-form-label']) }}>
      <input type="{{ $type === 'switch' ? 'checkbox' : $type }}" name="{{ $name }}" id="{{ $choiceId }}" class="custom-control-input"
        value="{{ $key }}" @if ($selected) checked="checked" @endif>
      <label class="custom-control-label form-check-label" for="{{ $choiceId }}">{{ $label }}</label>
    </div>
  @endforeach
@endif
