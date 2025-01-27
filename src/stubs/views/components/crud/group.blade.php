@props([
    'id', 'label', 'model' => null, 
    'appendIcon' => null, 'prependIcon' => null,
])
@aware([
    'model' => null,
])
<div {{ $attributes->merge(['class' => 'form-group']) }}>
    <label for="{{ $id }}" class="text-lightblue">{{ $label }}</label>
    <div class="input-group">
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