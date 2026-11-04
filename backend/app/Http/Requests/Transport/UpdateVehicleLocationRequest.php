<?php

namespace App\Http\Requests\Transport;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('transport.track')) {
            return false;
        }

        /** @var \App\Models\Vehicle $vehicle */
        $vehicle = $this->route('vehicle');

        return $vehicle->driver_user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
