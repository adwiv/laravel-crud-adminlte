@props([
    'theme' => 'primary', 'icon' => null, 'label' => null
])

<button {{ $attributes->merge(['class' => "btn btn-{$theme}"]) }}>
    @isset($icon) <i class="{{ $icon }}"></i> @endisset
    @isset($label) {{ $label }} @endisset
</button>
