@props(['name', 'label', 'type' => 'text', 'value' => '', 'noNumbers' => false, 'contactNumber' => false, 'digitsOnly' => false])
<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if($contactNumber) data-contact-number inputmode="numeric" pattern="[0-9]{11}" @endif @if($digitsOnly) data-digits-only inputmode="numeric" pattern="[0-9]+" @endif @if($noNumbers) data-no-numbers pattern="[^\p{Nd}]*" @endif @if($type !== 'password') value="{{ old($name, $value) }}" @endif {{ $attributes->merge(['class' => 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700']) }} @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
    @if($noNumbers)<p id="{{ $name }}-number-error" class="mt-2 text-sm text-red-700" aria-live="polite" hidden>Numbers are not allowed in this field.</p>@endif
    @if($contactNumber)<p id="{{ $name }}-number-error" class="mt-2 text-sm text-red-700" aria-live="polite" hidden>Enter exactly 11 digits, with no letters or symbols.</p>@endif
    @if($digitsOnly)<p id="{{ $name }}-number-error" class="mt-2 text-sm text-red-700" aria-live="polite" hidden>Only numbers are allowed in this field. Remove letters, spaces and symbols.</p>@endif
    @error($name)<p id="{{ $name }}-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
</div>
