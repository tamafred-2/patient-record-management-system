<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::orderByDesc('is_active')->orderBy('name')->orderBy('id')->get();
        $services = $services->reject(fn (Service $service) => $service->seed_key === 'OTHER')
            ->concat($services->filter(fn (Service $service) => $service->seed_key === 'OTHER'))
            ->values();

        return view('services.index', compact('services'));
    }

    public function store(Request $request)
    {
        $request->merge(['editing_id' => null]);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:services,code'], 'description' => ['nullable', 'string', 'max:1000'], 'is_active' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($data, $request) {
            $service = Service::create([...$data, 'is_active' => $data['is_active'] ?? true]);
            activity('services')->causedBy($request->user())->performedOn($service)->log('service.created');
        });

        return back()->with('status', 'Service created.');
    }

    public function update(Request $request, Service $service)
    {
        $request->merge(['editing_id' => $service->id]);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z][A-Z0-9_]*$/', Rule::unique('services', 'code')->ignore($service->id)], 'is_active' => ['required', 'boolean'], 'description' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($data, $request, $service) {
            $previousCode = $service->code;
            $service->update($data);
            activity('services')->causedBy($request->user())->performedOn($service)->withProperties(['is_active' => $service->is_active, 'previous_code' => $previousCode, 'code' => $service->code])->log('service.updated');
        });

        return back()->with('status', 'Service updated.');
    }

    public function destroy(Request $request, Service $service)
    {
        DB::transaction(function () use ($request, $service) {
            $service = Service::whereKey($service->id)->lockForUpdate()->firstOrFail();
            if (Visit::where('service_id', $service->id)->exists()) {
                throw ValidationException::withMessages(['delete' => 'This service is linked to visits and cannot be deleted. Edit it and set it to Inactive instead.']);
            }
            $service->delete();
            activity('services')->causedBy($request->user())->performedOn($service)->log('service.deleted');
        });

        return redirect()->route('services.index')->with('status', 'Service deleted.');
    }
}
