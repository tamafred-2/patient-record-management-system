@props(['name', 'label', 'value' => ''])
<div>
    <label for="{{ $attributes->get('id', $name) }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
    <textarea id="{{ $attributes->get('id', $name) }}" name="{{ $name }}" {{ $attributes->except('id')->merge(['rows' => 3, 'class' => 'w-full rounded-lg border border-slate-300 bg-white p-3 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700 disabled:bg-slate-50']) }} @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>{{ old($name, $value) }}</textarea>
    @error($name)<p id="{{ $name }}-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
</div>
