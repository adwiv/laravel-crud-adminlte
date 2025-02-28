@props([
    'method' => 'post',
    'model' => null,
])
@php
  $method = strtoupper($method);
  if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
      throw new \Exception("Invalid method: $method");
  }
@endphp
<form method="{{ $method == 'GET' ? 'GET' : 'POST' }}" {{ $attributes->merge(['class' => 'form']) }}>
  @if ($method != 'GET')
    @csrf
    @if ($method != 'POST')
      @method($method)
    @endif
  @endif
  {{ $slot }}
</form>
