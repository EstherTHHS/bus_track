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
            // Optional: the client usually knows the row it is editing. Scoped
            // to the caller's own trips so an id cannot reach someone else's.
            'legs.*.id' => [
                'nullable',
                'integer',
                Rule::exists('ferry_trips', 'id')->where('user_id', $this->user()?->id),
            ],
            'legs.*.leg' => ['required', Rule::in(FerryTrip::LEGS), 'distinct'],
            'legs.*.trip_time' => ['nullable', 'date_format:H:i'],
            'legs.*.status' => ['required', Rule::in(FerryTrip::STATUSES)],
            'legs.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
