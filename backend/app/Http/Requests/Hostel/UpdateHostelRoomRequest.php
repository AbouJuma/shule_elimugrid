<?php

namespace App\Http\Requests\Hostel;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateHostelRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hostel.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'hostel_id' => ['required', Rule::exists('hostels', 'id')->where('school_id', $schoolId)],
            'room_number' => [
                'required', 'string', 'max:50',
                Rule::unique('hostel_rooms', 'room_number')->where('hostel_id', $this->input('hostel_id'))->ignore($this->route('room')),
            ],
            'capacity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $room = $this->route('room');
            if ($room && $this->integer('capacity') < $room->occupied_count) {
                $validator->errors()->add('capacity', "Can't reduce capacity below the {$room->occupied_count} student(s) currently allocated.");
            }
        });
    }
}
