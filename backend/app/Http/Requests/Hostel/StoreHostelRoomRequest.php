<?php

namespace App\Http\Requests\Hostel;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHostelRoomRequest extends FormRequest
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
                Rule::unique('hostel_rooms', 'room_number')->where('hostel_id', $this->input('hostel_id')),
            ],
            'capacity' => ['required', 'integer', 'min:1'],
        ];
    }
}
