<x-layout title="Services">
    @php
        $initial = [
            'delete_url' => old('editing_id') ? route('services.destroy', old('editing_id')) : '',
            'id' => old('editing_id'), 'name' => old('name', ''), 'code' => old('code', ''),
            'description' => old('description', ''), 'is_active' => (string) old('is_active', '1'),
            'url' => old('editing_id') ? route('services.update', old('editing_id')) : route('services.store'),
        ];
    @endphp
    <section x-data="{ form: {{ Js::from($initial) }}, deleting: { name: '', url: '' }, showErrors: true,
        openEditor(data) { this.form = data; this.showErrors = false; this.$refs.serviceEditor.showModal() },
        openDelete(data) { this.deleting = data; this.$refs.serviceEditor.close(); this.$refs.deleteDialog.showModal() }
    }" x-init="@if($errors->any() && !$errors->has('delete')) $nextTick(() => $refs.serviceEditor.showModal()) @endif">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><h1 class="text-3xl font-semibold">Existing services</h1><p class="mt-2 text-slate-600">Services are alphabetical within active and inactive groups, with Other RHU Service always last.</p></div>
            <x-button type="button" @click="openEditor({ id: null, name: '', code: '', description: '', is_active: '1', url: '{{ route('services.store') }}' })">Add service</x-button>
        </div>
        @error('delete')<x-alert type="error" title="Unable to delete service" class="mt-5">{{ $message }}</x-alert>@enderror
        <div class="mt-8 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Configured RHU services and availability</caption>
                <thead class="bg-slate-50 text-slate-500"><tr><th scope="col" class="p-4">Service</th><th scope="col" class="p-4">Code</th><th scope="col" class="p-4">Status</th><th scope="col" class="p-4 text-left">Action</th></tr></thead>
                <tbody>
                    @forelse($services as $service)
                        <tr class="border-t border-slate-100">
                            <td class="p-4"><strong>{{ $service->name }}</strong>@if($service->description)<p class="mt-1 max-w-md break-words text-slate-500">{{ $service->description }}</p>@endif</td>
                            <td class="p-4 text-slate-500">{{ $service->code }}</td>
                            <td class="whitespace-nowrap p-4 text-slate-600">{{ $service->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="whitespace-nowrap p-4 text-left">
                                <button type="button" style="cursor: pointer;" class="cursor-pointer font-semibold text-emerald-800 underline" aria-label="Manage service: {{ $service->name }}" @click="openEditor({{ Js::from(['id' => $service->id, 'name' => $service->name, 'code' => $service->code, 'description' => $service->description ?? '', 'is_active' => $service->is_active ? '1' : '0', 'url' => route('services.update', $service), 'delete_url' => route('services.destroy', $service)]) }})">Manage service</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-8 text-center text-slate-500">No services yet. Add a service to get started.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-dialog reference="serviceEditor" labelledby="service-editor-title">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5"><h2 id="service-editor-title" class="text-xl font-semibold" x-text="form.id ? 'Manage service' : 'Add service'"></h2><button type="button" @click="$refs.serviceEditor.close()" aria-label="Close service form" class="rounded-lg px-3 py-1 text-xl text-slate-500 hover:bg-slate-100">&times;</button></div>
            <form method="POST" :action="form.url" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="_method" :value="form.id ? 'PATCH' : 'POST'">
                @if($errors->any() && !$errors->has('delete'))<div x-show="showErrors"><x-alert type="error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</x-alert></div>@endif
                <div><label for="name" class="mb-2 block text-sm font-medium">Service name (required)</label><input id="name" name="name" x-model="form.name" required maxlength="150" autofocus class="w-full rounded-lg border border-slate-300 p-3"></div>
                <div><label for="code" class="mb-2 block text-sm font-medium">Code (required)</label><input id="code" name="code" x-model="form.code" required maxlength="50" pattern="[A-Z][A-Z0-9_]*" class="w-full rounded-lg border border-slate-300 p-3"><p class="mt-1 text-xs text-slate-500">Use a unique code with uppercase letters, numbers and underscores; start with a letter.</p></div>
                <div><label for="description" class="mb-2 block text-sm font-medium">Description (optional)</label><textarea id="description" name="description" x-model="form.description" maxlength="1000" rows="3" class="w-full rounded-lg border border-slate-300 p-3"></textarea></div>
                <div><label for="is_active" class="mb-2 block text-sm font-medium">Status</label><select id="is_active" name="is_active" x-model="form.is_active" class="w-full rounded-lg border border-slate-300 p-3"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5"><button type="button" x-show="form.id" @click="openDelete({ name: form.name, url: form.delete_url })" class="mr-auto rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Delete service</button><button type="button" @click="$refs.serviceEditor.close()" class="rounded-lg px-4 py-2 text-sm font-semibold hover:bg-slate-100">Cancel</button><x-button><span x-text="form.id ? 'Save changes' : 'Add service'"></span></x-button></div>
            </form>
        </x-dialog>
        <x-dialog reference="deleteDialog" labelledby="delete-service-title">
            <form method="POST" :action="deleting.url" class="space-y-5 p-6">
                @csrf @method('DELETE')
                <h2 id="delete-service-title" class="text-xl font-semibold">Delete service?</h2>
                <p class="text-slate-600">Remove <strong x-text="deleting.name"></strong> from available services? Services linked to visits cannot be deleted; deactivate them instead.</p>
                <div class="flex justify-end gap-3"><button type="button" autofocus @click="$refs.deleteDialog.close()" class="rounded-lg px-4 py-2 text-sm font-semibold hover:bg-slate-100">Cancel</button><button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Delete service</button></div>
            </form>
        </x-dialog>
    </section>
</x-layout>
