@props(['name', 'label', 'options', 'value' => '', 'required' => false])
@php($selected = old($name, $value) ?? '')
<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}" @required($required) class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700" @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
        <option value="">{{ $required ? 'Select an option' : 'None / not specified' }}</option>
        @if($selected !== '' && !in_array($selected, $options, true))
            <option value="{{ $selected }}" selected>{{ $selected }} (current value)</option>
        @endif
        @foreach($options as $option)
            <option value="{{ $option }}" @selected($selected === $option)>{{ $option }}</option>
        @endforeach
    </select>
    @error($name)<p id="{{ $name }}-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
</div>
