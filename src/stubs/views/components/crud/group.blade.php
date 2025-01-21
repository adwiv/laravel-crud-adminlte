@props([
    'id', 'label',
    'igroupSize' => null, 'igroupClass' => null, 'fgroupClass' => null, 'flabelClass' => 'text-lightblue',
    'appendIcon' => null, 'prependIcon' => null,
])
@php
    $formGroupClass = "form-group" . ($fgroupClass ? " $fgroupClass" : '');
    $inputGroupClass = "input-group" . ($igroupSize ? " input-group-$igroupSize" : '') . ($igroupClass ? " $igroupClass" : '');

    $formGroupClass = "form-group" . ($fgroupClass ? " $fgroupClass" : '');
    $inputGroupClass = "input-group" . ($igroupSize ? " input-group-$igroupSize" : '') . ($igroupClass ? " $igroupClass" : '');
@endphp

<div class="{{ $formGroupClass }}">
    @if($label)
        <label for="{{ $id }}" class="{{ $flabelClass }}">{{ $label }}</label>
    @endif
    <div class="{{ $inputGroupClass }}">
        {{ $slot }}
    </div>
</div>

<div class="{{ $formGroupClass }}">
    @if($label)
        <label for="{{ $id }}" class="{{ $flabelClass }}">{{ $label }}</label>
    @endif
    <div class="{{ $inputGroupClass }}">
        @if(isset($prependSlot) || isset($prependIcon))
        <div class="input-group-prepend">
            @isset($prependSlot)
                {{ $prependSlot }}
            @endisset
            @isset($prependIcon)
                <div class="input-group-text">
                    <i class="{{ $prependIcon }} text-lightblue"></i>
                </div>
            @endisset
        </div>
        @endif
        {{ $slot }}
        @if(isset($appendSlot) || isset($appendIcon))
        <div class="input-group-append">
            @isset($appendSlot)
                {{ $appendSlot }}
            @endisset
            @isset($appendIcon)
                <div class="input-group-text">
                    <i class="{{ $appendIcon }} text-lightblue"></i>
                </div>
            @endisset
        </div>
        @endif
    </div>
</div>