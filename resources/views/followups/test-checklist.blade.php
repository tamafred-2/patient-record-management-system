@php
    // $name: input array name; $id: unique prefix; $legend: fieldset label; $only: optional list limiting the catalog;
    // $checked: optional preselected tests (otherwise the previous submission is restored).
    $only ??= null;
    $selected = isset($checked) ? $checked : old($name, []);
@endphp
<fieldset id="{{ $id }}" aria-describedby="{{ $id }}-help">
    <legend class="mb-1 font-medium">{{ $legend }}</legend>
    <p id="{{ $id }}-help" class="mb-3 text-sm text-slate-600">Select one or more tests. Each selected test is sent to the laboratory as its own Pending request.</p>
    <div class="max-h-72 space-y-4 overflow-y-auto rounded-lg border border-slate-200 p-3">
        @foreach(config('laboratory.tests') as $group => $tests)
            @php($tests = $only === null ? $tests : array_values(array_intersect($tests, $only)))
            @if($tests)
                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-700">{{ $group }}</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach($tests as $test)
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="{{ $name }}[]" value="{{ $test }}" @checked(in_array($test, (array) $selected, true)) class="mt-0.5">{{ $test }}</label>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
    @error($name)<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
</fieldset>
