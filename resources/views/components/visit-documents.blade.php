@props(['visit', 'documents' => null, 'heading' => 'Printouts'])
@php($documents ??= App\Support\PrintableDocuments::forVisit(auth()->user(), $visit))
@if($documents)
    <section {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-5']) }} aria-label="{{ $heading }} for {{ $visit->visit_number }}">
        <h2 class="font-semibold">{{ $heading }}</h2>
        <ul class="mt-3 divide-y divide-slate-100">
            @foreach($documents as $document)
                <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                    <span class="min-w-0">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $document['group'] }}</span>
                        <span class="block font-medium">{{ $document['label'] }}</span>
                        @if($document['note'])<span class="block truncate text-sm text-slate-500">{{ $document['note'] }}</span>@endif
                    </span>
                    @if($document['url'])
                        <a href="{{ $document['url'] }}" class="shrink-0 rounded-lg border border-emerald-800 px-3 py-1.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">{{ str_starts_with($document['label'], 'Prepare') ? 'Open' : 'Print' }}<span class="sr-only"> {{ $document['label'] }}</span></a>
                    @else
                        <span class="shrink-0 text-sm text-slate-500">Not available yet</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
