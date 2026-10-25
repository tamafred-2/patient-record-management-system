<x-layout title="Service report">
    <h1 class="text-3xl font-semibold">Service activity report</h1>
    <p class="mt-2 text-slate-600">Visits by requested service, not proof that services were performed. Archived patients are excluded.</p>
    @if($errors->any())<x-alert type="error" class="mt-5">{{ $errors->first() }}</x-alert>@endif
    <form method="GET" class="my-6 space-y-5 rounded-xl border border-slate-200 bg-white p-5" x-data="{ period: {{ Js::from(old('period', $period)) }} }">
        <input type="hidden" name="configured" value="1">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><label for="period" class="mb-2 block text-sm font-medium">Date range</label><select id="period" name="period" x-model="period" class="w-full rounded-lg border border-slate-300 p-3">@foreach(['custom'=>'Custom dates','today'=>'Today','week'=>'This week to date','month'=>'This month to date'] as $value=>$label)<option value="{{ $value }}" @selected(old('period', $period) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="from" class="mb-2 block text-sm font-medium">From date</label><input id="from" name="from" type="date" data-history-date data-range-days="365" max="{{ now('Asia/Manila')->toDateString() }}" value="{{ old('from',$from) }}" :disabled="period !== 'custom'" required class="w-full rounded-lg border border-slate-300 p-3 disabled:bg-slate-100"></div>
            <div><label for="to" class="mb-2 block text-sm font-medium">To date</label><input id="to" name="to" type="date" data-history-date data-range-days="365" max="{{ now('Asia/Manila')->toDateString() }}" value="{{ old('to',$to) }}" :disabled="period !== 'custom'" required class="w-full rounded-lg border border-slate-300 p-3 disabled:bg-slate-100"></div>
            <div><label for="status" class="mb-2 block text-sm font-medium">Visit status</label><select id="status" name="status" class="w-full rounded-lg border border-slate-300 p-3">@foreach(['all'=>'All statuses','OPEN'=>'Open','COMPLETED'=>'Completed'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$status)===$value)>{{ $label }}</option>@endforeach</select></div>
        </div>
        <fieldset><legend class="mb-2 font-medium">Services</legend><p class="mb-3 text-sm text-slate-500">Leave all unchecked to include every service.</p><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach($services as $service)<label class="flex items-start gap-2 text-sm"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, old('service_ids',$serviceIds))) class="mt-1">{{ $service->name }}{{ $service->deleted_at ? ' (archived service)' : '' }}</label>@endforeach</div></fieldset>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="format" class="mb-2 block text-sm font-medium">Report contents</label><select id="format" name="format" class="w-full rounded-lg border border-slate-300 p-3">@foreach(['summary'=>'Summary only','register'=>'Visit register only','both'=>'Summary and visit register'] as $value=>$label)<option value="{{ $value }}" @selected(old('format',$format)===$value)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="group" class="mb-2 block text-sm font-medium">Group summary by</label><select id="group" name="group" class="w-full rounded-lg border border-slate-300 p-3">@foreach(['service'=>'Requested service','day'=>'Day','month'=>'Month'] as $value=>$label)<option value="{{ $value }}" @selected(old('group',$group)===$value)>{{ $label }}</option>@endforeach</select></div>
        </div>
        <fieldset><legend class="mb-2 font-medium">Optional visit-register columns</legend><p class="mb-3 text-sm text-slate-500">Visit date, visit number, requested service and status are always included in the register.</p><div class="flex flex-wrap gap-4">@foreach($columnLabels as $value=>$label)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="columns[]" value="{{ $value }}" @checked(in_array($value,old('columns',$columns)))>{{ $label }}</label>@endforeach</div></fieldset>
        <p class="text-sm text-slate-500">Patient names, patient numbers, completion remarks and clinical text are excluded. Register PDFs support up to 2,000 visits per download.</p>
        <x-button class="cursor-pointer">Preview report</x-button>
    </form>
    @php($downloadTooLarge = $format !== 'summary' && $total > \App\Http\Controllers\ReportController::MAX_REGISTER_ROWS)
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold">Report preview</h2>
        @can('reports.export')
            @if($downloadTooLarge)
                <button type="button" disabled aria-describedby="download-limit" class="rounded-lg bg-slate-200 px-4 py-3 font-semibold text-slate-500">Download report PDF</button>
            @else
                <a class="rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white" href="{{ route('reports.pdf',$filters) }}">Download report PDF</a>
            @endif
        @endcan
    </div>
    @if($downloadTooLarge)
        <div id="download-limit" role="alert" class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-950">
            <p class="font-semibold">This register is too large for one download.</p>
            <p class="mt-2">Your selection contains {{ number_format($total) }} visits. The maximum is {{ number_format(\App\Http\Controllers\ReportController::MAX_REGISTER_ROWS) }} visits per register PDF. Choose smaller, non-overlapping date ranges, or narrow the service/status filters, then preview again.</p>
            @can('reports.export')<a class="mt-3 inline-block font-semibold text-emerald-800 underline" href="{{ route('reports.pdf', array_replace($filters, ['format' => 'summary'])) }}">Download summary only</a>@endcan
        </div>
    @endif
    <p class="mb-3 text-sm text-slate-500">Download limits: up to 366 dates per range, no future dates, and up to {{ number_format(\App\Http\Controllers\ReportController::MAX_REGISTER_ROWS) }} visits when a register is included. Downloads are validated again against current records.</p>
    <p class="mb-4 text-sm text-slate-500">Download uses the preview below. Select Preview report after changing your choices.</p>
    <div class="report-preview overflow-x-auto rounded-xl border border-slate-200 bg-white p-5">@include('reports.styles')<div class="service-report">@include('reports.content')</div></div>

</x-layout>
