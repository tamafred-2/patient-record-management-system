<x-layout title="Pharmacy">
    <h1 class="text-3xl font-semibold">Pharmacy</h1>
    <p class="mt-2 text-slate-600">Issued prescriptions by visit date (Asia/Manila).</p>
    <form method="GET" class="my-6 flex flex-wrap items-end gap-3">
        <div><label for="date" class="mb-2 block text-sm font-medium">Visit date</label><input id="date" name="date" type="date" value="{{ $date }}" required class="rounded-lg border border-slate-300 p-3"></div><x-button>Filter by date</x-button>
    </form>
    @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
    @include('pharmacy.table')
    <div class="mt-6">{{ $visits->links() }}</div>
</x-layout>
