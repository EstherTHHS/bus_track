<?php

namespace App\Http\Requests;

use App\Models\FerryTrip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFerryDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth:sanctum already blocks logged-out users
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trip_date' => ['required', 'date_format:Y-m-d'],
            'legs' => ['required', 'array', 'min:1', 'max:2'],
            'legs.*.leg' => ['required', Rule::in(FerryTrip::LEGS), 'distinct'],
            'legs.*.trip_time' => ['nullable', 'date_format:H:i'],
            'legs.*.status' => ['required', Rule::in(FerryTrip::STATUSES)],
            'legs.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
