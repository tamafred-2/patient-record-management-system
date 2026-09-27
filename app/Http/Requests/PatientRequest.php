<?php

namespace App\Http\Requests;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('patient');

        return $patient ? $this->user()->can('update', $patient) : $this->user()->can('create', Patient::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (Patient::DEMOGRAPHICS as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field)) ?: null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sex' => ['nullable', 'string', 'max:50'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'barangay' => ['nullable', 'string', 'max:150'],
            'municipality' => ['nullable', 'string', 'max:150'],
            'province' => ['nullable', 'string', 'max:150'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'string', 'max:40'],
            'confirm_distinct' => ['sometimes', 'boolean'],
            'lock_version' => [$this->route('patient') ? 'required' : 'prohibited', 'integer', 'min:1'],
            'patient_number' => ['prohibited'],
            'created_by' => ['prohibited'],
            'id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'duplicate_key' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }
}
