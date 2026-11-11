<?php

namespace App\Http\Requests;

use App\Enums\DisabilityType;
use App\Enums\FeedbackMode;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rfid_uid' => Student::normalizeUid($this->input('rfid_uid')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'nis' => ['required', 'string', 'max:30', Rule::unique('students', 'nis')->ignore($student)],
            'nisn' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'disability_type' => ['nullable', Rule::enum(DisabilityType::class)],
            'feedback_mode' => ['required', Rule::enum(FeedbackMode::class)],
            'rfid_uid' => ['nullable', 'string', 'max:64', Rule::unique('students', 'rfid_uid')->ignore($student)],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rfid_uid.unique' => 'Kartu ini sudah dipakai siswa lain.',
        ];
    }
}
