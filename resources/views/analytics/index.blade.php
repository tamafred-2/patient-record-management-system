<x-layout title="Analytics">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-3xl font-semibold">{{ $overview ? 'RHU analytics' : 'My department analytics' }}</h1><p class="mt-2 text-slate-600">{{ $overview ? 'Patient trends and service activity across the RHU.' : 'Operational summaries limited to your assigned modules.' }}</p></div>
        <span class="rounded-full bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-900">{{ $overview ? 'Admin overview' : 'Department view' }}</span>
    </div>
    @if($errors->any())<x-alert type="error" class="mt-5">{{ $errors->first() }}</x-alert>@endif
    <form method="GET" class="my-6 flex flex-wrap items-end gap-4">
        @foreach(['from'=>'From date','to'=>'To date'] as $key=>$label)
            <div><label for="{{ $key }}" class="mb-2 block text-sm font-medium">{{ $label }}</label><input type="date" id="{{ $key }}" name="{{ $key }}" value="{{ old($key,$$key) }}" required class="max-w-full rounded-lg border border-slate-300 bg-white p-3"></div>
        @endforeach
        <input type="hidden" name="interval" value="{{ $interval }}">
        <x-button>Update analytics</x-button>
    </form>
    <p class="mb-6 text-sm text-slate-600">Grouped by visit date (Asia/Manila), including both range endpoints. Archived patients are excluded. Counts reflect current record states for those visits, not the dates users entered them.</p>
    @if($trend)
        <section class="mb-6 min-w-0 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" x-data="{ hover: null, pinned: null, get selected() { return this.pinned ?? this.hover; } }" @keydown.escape="hover = null; pinned = null">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ ucfirst($interval) }} patient visits</h2>
                <nav aria-label="Chart interval" class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1">
                    @foreach(['daily','weekly','monthly','yearly'] as $option)
                        <a href="{{ route('analytics.index', ['from' => $from, 'to' => $to, 'interval' => $option]) }}" @if($interval === $option) aria-current="true" @endif class="rounded-lg px-3 py-2 text-sm font-medium {{ $interval === $option ? 'bg-white text-emerald-800 shadow-sm' : 'text-slate-600 hover:bg-white' }}">{{ ucfirst($option) }}</a>
                    @endforeach
                </nav>
            </div>
            <p class="mb-5 mt-2 text-sm text-slate-600">Repeat visits count separately. Weekly periods start Monday; partial periods include only the selected dates.</p>
            @php
                $dailyTrend = $trend; $buckets = [];
                foreach ($dailyTrend as $date => $count) {
                    $day = \Carbon\CarbonImmutable::parse($date);
                    $key = match ($interval) {
                        'weekly' => $day->startOfWeek(1)->toDateString(),
                        'monthly' => $day->startOfMonth()->toDateString(),
                        'yearly' => $day->startOfYear()->toDateString(),
                        default => $date,
                    };
                    if (!isset($buckets[$key])) $buckets[$key] = ['from' => $date, 'to' => $date, 'count' => 0];
                    $buckets[$key]['to'] = $date; $buckets[$key]['count'] += $count;
                }
                $buckets = array_values($buckets);
                $trend = array_column($buckets, 'count', 'from');
                $tick = max(1, (int) ceil(max($trend) / 4)); $maximum = $tick * 4;
                $points = []; $index = 0; $dates = array_keys($trend);
                foreach ($trend as $date => $count) { $points[] = (48 + $index * 704 / max(1, count($trend)-1)).','. (216 - $count * 180 / $maximum); $index++; }
            @endphp
            <div class="mb-4 flex flex-wrap gap-x-8 gap-y-2 text-sm text-slate-500">
                <span>Total visits <strong class="ml-2 text-slate-900">{{ number_format(array_sum($trend)) }}</strong></span>
                <span>Period average <strong class="ml-2 text-slate-900">{{ number_format(array_sum($trend) / count($trend), 1) }}</strong></span>
                <span>Peak period <strong class="ml-2 text-slate-900">{{ max($trend) }}</strong></span>
            </div>
            <div class="relative rounded-xl bg-white p-3">
            <div x-cloak x-show="pinned" class="absolute right-4 top-4 z-10 max-w-[85%] rounded-xl border border-slate-200 bg-white p-3 text-slate-900 shadow-xl" @click.stop>
                <p class="text-sm" x-text="pinned?.label"></p>
                <div class="mt-3 flex items-center gap-3">
                    <a :href="pinned?.url" class="rounded-lg bg-emerald-800 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-900">Go to this data</a>
                    <button type="button" @click="pinned = null; hover = null" aria-label="Clear selected period" class="rounded p-2 text-sm hover:bg-slate-100">Close</button>
                </div>
            </div>
            <div class="overflow-x-auto" @mouseleave="hover = null">
            <svg viewBox="0 0 800 265" role="group" aria-labelledby="chart-title" class="w-full min-w-[560px]"><title id="chart-title">{{ ucfirst($interval) }} visit counts from {{ $from }} to {{ $to }}. Hover, click or focus a point to view its date and visit count.</title>
                <defs><linearGradient id="visits-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#059669" stop-opacity="0.2"/><stop offset="100%" stop-color="#059669" stop-opacity="0.02"/></linearGradient></defs>
                <text x="48" y="16" font-size="11" fill="#64748b">Visits</text>
                @for($step=0; $step<=4; $step++)
                    @php($y = 216 - $step * 45)
                    <path d="M48 {{ $y }}H752" stroke="#e2e8f0" stroke-dasharray="4 4"/>
                    <text x="36" y="{{ $y + 4 }}" text-anchor="end" font-size="11" fill="#64748b">{{ $step * $tick }}</text>
                @endfor
                <polygon points="48,216 {{ implode(' ', $points) }} {{ count($trend) > 1 ? 752 : 48 }},216" fill="url(#visits-fill)"/>
                <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="#047857" stroke-width="2" stroke-linejoin="round"/>
                @foreach($points as $point)
                    @php([$cx,$cy]=explode(',',$point))
                    @php($datum = ['x' => (float) $cx, 'y' => (float) $cy, 'index' => $loop->index, 'label' => $buckets[$loop->index]['from'].($buckets[$loop->index]['from'] !== $buckets[$loop->index]['to'] ? ' to '.$buckets[$loop->index]['to'] : '').': '.array_values($trend)[$loop->index].' visits', 'url' => route('visits.index', ['from' => $buckets[$loop->index]['from'], 'to' => $buckets[$loop->index]['to']])])
                    <g role="button" tabindex="0" aria-label="{{ $datum['label'] }}" :aria-pressed="(pinned?.index === {{ $loop->index }}).toString()"
                        @mouseenter="hover = @js($datum)" @focus="hover = @js($datum)" @blur="hover = null"
                        @click="pinned = @js($datum); hover = null" @keydown.enter.prevent="pinned = @js($datum); hover = null" @keydown.space.prevent="pinned = @js($datum); hover = null"
                        class="cursor-pointer outline-none">
                        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="8" fill="transparent" />
                        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ count($trend) <= 45 ? 3 : 1.5 }}" fill="#047857" />
                    </g>
                @endforeach
                @foreach(array_unique([0, (int) floor((count($trend)-1)/4), (int) floor((count($trend)-1)/2), (int) floor((count($trend)-1)*3/4), count($trend)-1]) as $position)
                    <text x="{{ 48 + $position * 704 / max(1,count($trend)-1) }}" y="242" text-anchor="{{ $position === 0 ? 'start' : ($position === count($trend)-1 ? 'end' : 'middle') }}" font-size="11" fill="#64748b">{{ \Carbon\CarbonImmutable::parse($dates[$position])->format('M j') }}</text>
                @endforeach
                <g x-cloak x-show="selected" pointer-events="none" aria-hidden="true">
                    <path :d="`M${selected?.x ?? 0} 36V216 M48 ${selected?.y ?? 0}H752`" stroke="#64748b" stroke-dasharray="3 3" />
                    <circle :cx="selected?.x ?? 0" :cy="selected?.y ?? 0" r="5" fill="#fff" stroke="#047857" stroke-width="2" />
                    <g x-show="!pinned" :transform="`translate(${Math.min(472, Math.max(48, (selected?.x ?? 48) - 140))},${Math.max(0, (selected?.y ?? 0) - 44)})`">
                        <rect width="280" height="32" rx="7" fill="#064e3b" />
                        <text x="140" y="21" text-anchor="middle" fill="white" font-size="12" x-text="selected?.label ?? ''"></text>
                    </g>
                </g>
            </svg></div></div>
            <p role="status" aria-live="polite" class="mt-2 min-h-6 text-sm font-medium text-emerald-900" x-text="selected?.label ?? 'Hover or select a point to view its count.'"></p>
            <p class="mt-2 text-xs text-slate-500">Counts from {{ $from }} to {{ $to }}. Hover for exact dates and visits; click or tap a point, then choose Go to this data to open the patient visit list for that period. Use Tab and Enter with a keyboard, or Escape to clear. Scroll horizontally on small screens.</p>
        </section>
    @endif
    <div class="grid gap-6 lg:grid-cols-2">
        @forelse($panels as $title=>$rows)
            <x-analytics-card :title="$title">
                <x-slot:chart><x-analytics-bars :rows="$rows" :label="$title" /></x-slot:chart>
                <x-slot:counts>
                <dl class="mt-4 space-y-4">@forelse($rows as $label=>$value)<div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3"><dt class="text-sm text-slate-600">{{ $label }}</dt><dd class="text-xl font-semibold tabular-nums text-emerald-900">{{ number_format($value) }}</dd></div>@empty<p class="text-sm text-slate-500">No records in this date range.</p>@endforelse</dl></x-slot:counts>
            </x-analytics-card>
        @empty<p class="text-slate-600">No analytics module is assigned to your account.</p>@endforelse
    </div>
    @if($diseases !== null)
        <x-analytics-card title="Most recorded diagnoses" class="mt-6" :description="'Top 10 doctor-entered labels by visit count. '.$diagnosisCoverage['with'].' saved ITRs have diagnoses; '.$diagnosisCoverage['without'].' have none. Patients can appear in several categories. These are not community disease prevalence estimates.'">
            <x-slot:chart>
                <x-analytics-bars :rows="collect($diseases)->pluck('visits', 'label')->all()" label="Most recorded diagnoses" />
                <p class="mt-4 text-xs text-slate-500">Bars count visits. Flip for distinct patient counts. Capitalization and spacing are combined; spelling variants remain separate.</p>
            </x-slot:chart>
            <x-slot:counts>
            @if(!$diseases)<p class="mt-5 text-slate-500">No structured diagnoses recorded in this range. Doctors can add them when saving the ITR.</p>@else
            <div class="mt-5 overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Most recorded diagnosis labels</caption><thead><tr><th scope="col" class="p-3">Diagnosis label</th><th scope="col" class="p-3">Visits</th><th scope="col" class="p-3">Distinct patients</th></tr></thead><tbody>@foreach($diseases as $row)<tr class="border-t border-slate-100"><th scope="row" class="p-3 font-medium">{{ $row['label'] }}</th><td class="p-3">{{ $row['visits'] }}</td><td class="p-3">{{ $row['patients'] }}</td></tr>@endforeach</tbody></table></div>@endif
            </x-slot:counts>
        </x-analytics-card>
    @endif
    @if($screening !== null)
        <x-analytics-card title="ITR screening responses" description="These are recorded yes/no screening answers, not confirmed disease diagnoses. Counts are per saved ITR; unanswered responses are shown separately." class="mt-6">
            <x-slot:chart>
            <div class="mt-6 grid gap-5 sm:grid-cols-2" role="group" aria-label="ITR screening responses chart">
                @foreach($screening as $row)
                    @php($total = $row['yes'] + $row['no'] + $row['unanswered'])
                    <div>
                        <p class="text-sm font-medium text-slate-700">{{ $row['label'] }}</p>
                        <div aria-hidden="true" class="mt-2 flex h-4 overflow-hidden rounded-full bg-slate-100">
                            <span class="bg-emerald-600" style="width: {{ $row['yes'] / max(1, $total) * 100 }}%"></span>
                            <span class="bg-sky-600" style="width: {{ $row['no'] / max(1, $total) * 100 }}%"></span>
                            <span class="bg-slate-300" style="width: {{ $row['unanswered'] / max(1, $total) * 100 }}%"></span>
                        </div>
                        <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600"><span>Yes: {{ $row['yes'] }}</span><span>No: {{ $row['no'] }}</span><span>Unanswered: {{ $row['unanswered'] }}</span></p>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-xs text-slate-500">Each bar shows all saved ITRs: green = Yes, blue = No, gray = Unanswered. Empty bars mean no saved ITRs.</p>
            </x-slot:chart><x-slot:counts>
            <div class="mt-5 overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Screening responses among saved ITRs</caption><thead><tr>@foreach(['Screening item','Yes','No','Unanswered'] as $label)<th scope="col" class="p-3">{{ $label }}</th>@endforeach</tr></thead><tbody>@foreach($screening as $row)<tr class="border-t border-slate-100"><th scope="row" class="p-3 font-medium">{{ $row['label'] }}</th><td class="p-3">{{ $row['yes'] }}</td><td class="p-3">{{ $row['no'] }}</td><td class="p-3">{{ $row['unanswered'] }}</td></tr>@endforeach</tbody></table></div></x-slot:counts>
        </x-analytics-card>
    @endif
</x-layout>
