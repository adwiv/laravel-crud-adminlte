@props([
    'model' => null,
])
@php
  if ($model && !is_object($model) && !is_array($model)) {
      throw new \Exception('Model must be an object or an array');
  }
@endphp
<div {{ $attributes }}>
  {{ $slot }}
</div>
