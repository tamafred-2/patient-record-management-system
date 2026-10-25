@props(['date' => null, 'from' => null, 'to' => null, 'range' => false, 'allDates' => false])
<div x-data="{ all: {{ $allDates ? 'true' : 'false' }} }" class="min-w-0" :class="all ? 'max-w-[180px]' : ''">
    <label for="date-scope" class="mb-2 block text-sm font-medium">{{ $range ? 'Date scope' : 'Visit date' }}</label>
    <div class="flex flex-wrap items-end gap-2">
        <select id="date-scope" name="all" @change="all = $event.target.value === '1'" class="min-w-[135px] flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700">
            <option value="0" @selected(!$allDates)>{{ $range ? 'Specific dates' : 'Specific date' }}</option>
            <option value="1" @selected($allDates)>All dates</option>
        </select>
        <div x-show="!all" @if($allDates) x-cloak @endif class="flex flex-wrap gap-2">
            @foreach($range ? ['from' => ['From date', $from], 'to' => ['To date', $to]] : ['date' => ['Specific date', $date]] as $name => $field)
                <div>
                    <label for="{{ $name }}" class="{{ $range ? 'mb-2 block text-sm font-medium' : 'sr-only' }}">{{ $field[0] }}</label>
                    <input id="{{ $name }}" name="{{ $name }}" type="date" data-history-date @if($range) data-range-days="365" @endif max="{{ now('Asia/Manila')->toDateString() }}" value="{{ $field[1] }}" :disabled="all" :required="!all" @disabled($allDates) @required(!$allDates) class="w-40 rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700">
                </div>
            @endforeach
        </div>
    </div>
</div>
