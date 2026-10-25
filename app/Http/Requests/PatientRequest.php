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
                $value = trim($this->input($field));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100', 'not_regex:/\p{Nd}/u'],
            'last_name' => ['required', 'string', 'max:100', 'not_regex:/\p{Nd}/u'],
            'middle_name' => ['nullable', 'string', 'max:100', 'not_regex:/\p{Nd}/u'],
            'suffix' => ['nullable', 'string', 'max:30', 'not_regex:/\p{Nd}/u'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sex' => ['required', 'string', 'max:50'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'contact_number' => ['nullable', 'string', 'regex:/\A[0-9]{11}\z/'],
            'barangay' => ['required', 'string', 'max:150'],
            'municipality' => ['required', 'string', 'max:150'],
            'province' => ['required', 'string', 'max:150'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255', 'not_regex:/\p{Nd}/u'],
            'emergency_contact_number' => ['nullable', 'string', 'regex:/\A[0-9]{11}\z/'],
            'guardian_name' => ['nullable', 'required_with:guardian_relationship,guardian_phone', 'string', 'max:255', 'not_regex:/\p{Nd}/u'],
            'guardian_relationship' => ['nullable', 'required_with:guardian_name,guardian_phone', 'string', 'max:100', 'not_regex:/\p{Nd}/u'],
            'guardian_phone' => ['nullable', 'string', 'regex:/\A[0-9]{11}\z/'],
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

    public function messages(): array
    {
        return [
            'contact_number.regex' => 'The contact number must contain exactly 11 digits (0-9), with no letters or symbols.',
            'emergency_contact_number.regex' => 'The emergency contact number must contain exactly 11 digits (0-9), with no letters or symbols.',
            'guardian_phone.regex' => 'The guardian contact number must contain exactly 11 digits (0-9), with no letters or symbols.',
            'first_name.not_regex' => 'The first name must not contain numbers.',
            'last_name.not_regex' => 'The last name must not contain numbers.',
            'middle_name.not_regex' => 'The middle name must not contain numbers.',
            'suffix.not_regex' => 'The suffix must not contain numbers.',
            'emergency_contact_name.not_regex' => 'The emergency contact name must not contain numbers.',
            'guardian_name.not_regex' => 'The guardian name must not contain numbers.',
            'guardian_relationship.not_regex' => 'The guardian relationship must not contain numbers.',
        ];
    }
}
