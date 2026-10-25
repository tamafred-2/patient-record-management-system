@props(['patient', 'visit'])
@php($canChange = $visit->status === 'OPEN' && auth()->user()->can('visits.prioritize'))
<span class="inline-flex items-center gap-2 align-middle" x-data="{}" @if($errors->hasAny(['priority', 'priority_reason', 'priority_note'])) x-init="$nextTick(() => $refs.priorityDialog.showModal())" @endif>
    @if($visit->priority === 'REGULAR')
        <span class="rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-600">Regular</span>
    @else
        <x-visit-priority :visit="$visit" />
    @endif
    @if($canChange)
        <button type="button" @click="$refs.priorityDialog.showModal()" class="text-xs font-semibold text-emerald-800 underline" aria-haspopup="dialog">Change<span class="sr-only"> visit priority</span></button>
        <x-dialog reference="priorityDialog" labelledby="priority-dialog-title">
            <form method="POST" action="{{ route('visits.priority', [$patient, $visit]) }}" class="space-y-4 p-6 text-base font-normal">
                @csrf @method('PUT')
                <h2 id="priority-dialog-title" class="text-xl font-semibold">Change visit priority</h2>
                <x-visit-priority-fields :visit="$visit" id-prefix="dialog_priority" />
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <x-button variant="neutral" type="button" x-on:click="$refs.priorityDialog.close()">Cancel</x-button>
                    <x-button>Save priority</x-button>
                </div>
            </form>
        </x-dialog>
    @endif
</span>
