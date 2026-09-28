# bus_track — Ferry Tracker Backend: Step-by-Step Implementation

Laravel 13 + Sanctum API for recording ferry trips per leg and counting ferry days per month.

## How counting works

Each trip leg (morning or evening) is one record with a time and a status.

| Leg | Status | Count |
|---|---|---|
| Morning | taken | 0.5 |
| Evening | taken | 0.5 |
| Any leg | missed / cancelled | 0 |

A day with both legs taken = **1.0** (full round). One leg only = **0.5**. At month end, the summary adds everything up (e.g. `18.5` days).

## Endpoints (final result)

| Method | URL | Auth | Purpose |
|---|---|---|---|
| POST | `/api/register` | No | Create account, returns token |
| POST | `/api/login` | No | Log in, returns token |
| GET | `/api/me` | Yes | Current user |
| POST | `/api/logout` | Yes | Revoke current token |
| GET | `/api/ferry-trips?month=2026-09` | Yes | Days in month with both legs and day count |
| GET | `/api/ferry-trips/summary?month=2026-09` | Yes | Month totals |
| POST | `/api/ferry-trips/day` | Yes | Save morning and/or evening for a date |
| DELETE | `/api/ferry-trips/{id}` | Yes | Delete one leg |

---

## Step 1: Install the API layer and Sanctum

```bash
php artisan install:api
```

Say **yes** when asked to run migrations. This creates `routes/api.php`, installs Sanctum, and creates the `personal_access_tokens` table.

- [ ] **Check:** `routes/api.php` exists, and `bootstrap/app.php` has an `api:` line inside `withRouting(...)`.

---

## Step 2: Give the User model tokens

In `app/Models/User.php`, add the imports, the `HasApiTokens` trait, and the relation:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;   // add
use Laravel\Sanctum\HasApiTokens;                      // add

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;          // add HasApiTokens

    // ... keep casts() as is ...

    public function ferryTrips(): HasMany              // add
    {
        return $this->hasMany(FerryTrip::class);
    }
}
```

---

## Step 3: Create the FerryTrip model and table

```bash
php artisan make:model FerryTrip -m
```

### 3a. Migration

In `database/migrations/xxxx_xx_xx_xxxxxx_create_ferry_trips_table.php`, replace `up()`:

```php
public function up(): void
{
    Schema::create('ferry_trips', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->date('trip_date');
        $table->enum('leg', ['morning', 'evening']);
        $table->time('trip_time')->nullable();
        $table->enum('status', ['taken', 'missed', 'cancelled'])->default('taken');
        $table->string('note')->nullable();
        $table->timestamps();

        // One morning + one evening per day, per user.
        $table->unique(['user_id', 'trip_date', 'leg']);
    });
}
```

### 3b. Model

Replace `app/Models/FerryTrip.php`:

```php
<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FerryTrip extends Model
{
    public const LEGS = ['morning', 'evening'];
    public const STATUSES = ['taken', 'missed', 'cancelled'];

    // user_id is NOT fillable: always set through $user->ferryTrips()
    protected $fillable = ['trip_date', 'leg', 'trip_time', 'status', 'note'];

    protected $casts = ['trip_date' => 'date:Y-m-d'];

    protected $hidden = ['user_id'];

    protected $appends = ['count'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Each leg is half a day, only when taken.
    public function getCountAttribute(): float
    {
        return $this->status === 'taken' ? 0.5 : 0.0;
    }

    // Date range instead of whereMonth() so the database can use the index.
    public function scopeInMonth(Builder $query, Carbon $monthStart): Builder
    {
        return $query->whereBetween('trip_date', [
            $monthStart->copy()->startOfMonth()->toDateString(),
            $monthStart->copy()->endOfMonth()->toDateString(),
        ]);
    }
}
```

---

## Step 4: Run the migration

```bash
php artisan migrate
```

- [ ] **Check:** output lists `create_ferry_trips_table` as DONE.

---

## Step 5: Create the AuthController

```bash
php artisan make:controller Api/AuthController
```

Replace `app/Http/Controllers/Api/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/register
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'], // needs password_confirmation
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'], // hashed by the User model's 'hashed' cast
        ]);

        return response()->json([
            'user'  => $user,
            'token' => $user->createToken('react-app')->plainTextToken,
        ], 201);
    }

    /**
     * POST /api/login
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'user'  => $user,
            'token' => $user->createToken('react-app')->plainTextToken,
        ]);
    }

    /**
     * GET /api/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * POST /api/logout — revokes only the token used for this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
```

> The User model already has the `'password' => 'hashed'` cast, so `register()` passes the password as-is.

---

## Step 6: Add auth routes and test login

Replace `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
```

Start the server:

```bash
php artisan serve
```

Test in Postman. **Every request needs the header `Accept: application/json`.**

1. `POST http://localhost:8000/api/register`

   ```json
   {
     "name": "Esther",
     "email": "esther@test.com",
     "password": "password123",
     "password_confirmation": "password123"
   }
   ```

   Expect **201** with a `token`. Copy it.

2. `GET http://localhost:8000/api/me` with Authorization → Bearer Token → paste token. Expect your user.
3. Same request without the token. Expect **401**.

- [ ] **Check:** all three results match before moving on.

---

## Step 7: Create the validation request

```bash
php artisan make:request StoreFerryDayRequest
```

Replace `app/Http/Requests/StoreFerryDayRequest.php`:

```php
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

    public function rules(): array
    {
        return [
            'trip_date'        => ['required', 'date_format:Y-m-d'],
            'legs'             => ['required', 'array', 'min:1', 'max:2'],
            'legs.*.leg'       => ['required', Rule::in(FerryTrip::LEGS), 'distinct'],
            'legs.*.trip_time' => ['nullable', 'date_format:H:i'],
            'legs.*.status'    => ['required', Rule::in(FerryTrip::STATUSES)],
            'legs.*.note'      => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

---

## Step 8: Create the FerryTripController

```bash
php artisan make:controller Api/FerryTripController
```

Replace `app/Http/Controllers/Api/FerryTripController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFerryDayRequest;
use App\Models\FerryTrip;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FerryTripController extends Controller
{
    /**
     * GET /api/ferry-trips?month=2026-09
     */
    public function index(Request $request): JsonResponse
    {
        $start = $this->monthFromRequest($request);

        $trips = FerryTrip::whereBelongsTo($request->user())
            ->inMonth($start)
            ->orderBy('trip_date')
            ->get();

        $days = $trips
            ->groupBy(fn (FerryTrip $t) => $t->trip_date->format('Y-m-d'))
            ->map(fn ($legs, $date) => [
                'date'      => $date,
                'morning'   => $legs->firstWhere('leg', 'morning'),
                'evening'   => $legs->firstWhere('leg', 'evening'),
                'day_count' => $legs->sum('count'),
            ])
            ->values();

        return response()->json([
            'month' => $start->format('Y-m'),
            'days'  => $days,
        ]);
    }

    /**
     * GET /api/ferry-trips/summary?month=2026-09
     */
    public function summary(Request $request): JsonResponse
    {
        $start = $this->monthFromRequest($request);

        $daily = FerryTrip::whereBelongsTo($request->user())
            ->inMonth($start)
            ->selectRaw("trip_date, SUM(CASE WHEN status = 'taken' THEN 0.5 ELSE 0 END) AS day_count")
            ->groupBy('trip_date');

        $row = DB::query()
            ->fromSub($daily, 'd')
            ->selectRaw('
                COALESCE(SUM(day_count), 0)       AS total_days,
                COALESCE(SUM(day_count = 1), 0)   AS full_days,
                COALESCE(SUM(day_count = 0.5), 0) AS half_days
            ')
            ->first();

        return response()->json([
            'month'      => $start->format('Y-m'),
            'total_days' => (float) $row->total_days,
            'full_days'  => (int) $row->full_days,
            'half_days'  => (int) $row->half_days,
        ]);
    }

    /**
     * POST /api/ferry-trips/day
     */
    public function storeDay(StoreFerryDayRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $trips = DB::transaction(function () use ($data, $user) {
            // Going through the relation sets user_id automatically.
            return collect($data['legs'])->map(fn ($leg) => $user->ferryTrips()->updateOrCreate(
                ['trip_date' => $data['trip_date'], 'leg' => $leg['leg']],
                [
                    'trip_time' => $leg['trip_time'] ?? null,
                    'status'    => $leg['status'],
                    'note'      => $leg['note'] ?? null,
                ]
            ));
        });

        return response()->json([
            'date'      => $data['trip_date'],
            'trips'     => $trips,
            'day_count' => $trips->sum('count'),
        ], 201);
    }

    /**
     * DELETE /api/ferry-trips/{ferryTrip}
     */
    public function destroy(Request $request, FerryTrip $ferryTrip): JsonResponse
    {
        // Someone else's record: pretend it doesn't exist.
        abort_unless($ferryTrip->user_id === $request->user()->id, 404);

        $ferryTrip->delete();

        return response()->json(null, 204);
    }

    private function monthFromRequest(Request $request): Carbon
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->month)->startOfMonth()
            : now()->startOfMonth();
    }
}
```

---

## Step 9: Add the ferry routes

At the top of `routes/api.php`, add:

```php
use App\Http\Controllers\Api\FerryTripController;
```

Inside the `auth:sanctum` group, below the logout route, add:

```php
    Route::prefix('ferry-trips')->controller(FerryTripController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/summary', 'summary');
        Route::post('/day', 'storeDay');
        Route::delete('/{ferryTrip}', 'destroy');
    });
```

```bash
php artisan route:list --path=api
```

- [ ] **Check:** 8 routes listed.

---

## Step 10: Test the ferry flow

Use the Bearer token and `Accept: application/json` on every request.

1. **Full day** — `POST /api/ferry-trips/day`

   ```json
   {
     "trip_date": "2026-09-28",
     "legs": [
       { "leg": "morning", "trip_time": "07:45", "status": "taken" },
       { "leg": "evening", "trip_time": "18:10", "status": "taken" }
     ]
   }
   ```

   Expect `"day_count": 1`.

2. **Half day** — post another date with only the morning leg. Expect `"day_count": 0.5`.

3. **Summary** — `GET /api/ferry-trips/summary?month=2026-09`. Expect:

   ```json
   { "month": "2026-09", "total_days": 1.5, "full_days": 1, "half_days": 1 }
   ```

4. **List** — `GET /api/ferry-trips?month=2026-09`. Expect both days with their legs.

- [ ] **Check:** all four match, then commit and push.

---

## Next

- Build the React screens (login, daily entry with Morning/Evening rows, monthly calendar, summary).
- Optional: add a `mode` column (ferry / bus) to track both in one table.
