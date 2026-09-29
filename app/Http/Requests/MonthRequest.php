<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class MonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    /**
     * The first day of the requested month, defaulting to the current one.
     *
     * The leading "!" resets the unspecified day and time to their epoch
     * defaults. Without it, "2026-02" inherits today's day-of-month and
     * overflows into March when today is the 29th or later.
     */
    public function monthStart(): Carbon
    {
        if (! $this->filled('month')) {
            return now()->startOfMonth();
        }

        return Carbon::createFromFormat('!Y-m', (string) $this->input('month'))->startOfMonth();
    }
}
