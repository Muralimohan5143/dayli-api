<?php

namespace App\Http\Livewire\Signup;

use App\Http\Livewire\Signup\VendorSignupWizard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use App\Models\User;
use App\Models\Address; // ⬅️ add this
use Livewire\Attributes\On;

class VendorProfileDetails extends Component
{
    public ?int $userId = null;
    public ?User $user  = null;

    // Core fields
    public string $first_name = '';
    public string $last_name  = '';
    public string $phone      = '';
    public string $email      = '';

    // Address
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city          = '';
    public string $pincode       = '';

    // Geo
    public ?float $lat = null;
    public ?float $lng = null;

    // Optional mapping
    public string $nagar = '';
    public string $zone  = '';   // can be a zone name or a numeric id; we’ll coerce below

    protected function rules(): array
    {
        return [
            'first_name'    => ['required', 'string', 'max:80'],
            'last_name'     => ['required', 'string', 'max:80'],
            'phone'         => ['required', 'min:10'],
            'email'         => ['required', 'email'],

            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['required', 'string', 'max:100'],
            'pincode'       => ['required', 'regex:/^\d{6}$/'],

            'lat'           => ['required', 'numeric', 'between:-90,90'],
            'lng'           => ['required', 'numeric', 'between:-180,180'],

            'nagar'         => ['nullable', 'string', 'max:120'],
            'zone'          => ['nullable', 'string', 'max:120'],
        ];
    }

    public function updated($prop): void
    {
        $live = [
            'first_name','last_name','phone','email',
            'address_line1','pincode','lat','lng'
        ];
        if (in_array($prop, $live, true)) {
            $this->validateOnly($prop, $this->rules());
        }
    }

    public function updatedPincode($value): void
    {
        $pin = preg_replace('/\D/', '', (string)$value);
        if (strlen($pin) === 6) {
            $this->fillCityFromPincode($pin);
        }
    }

    public function fillCityFromPincode(?string $pin = null): void
    {
        $pin = $pin ?? $this->pincode;
        if (!$pin || strlen($pin) !== 6) return;

        try {
            $resp = @file_get_contents("https://api.postalpincode.in/pincode/{$pin}");
            if ($resp) {
                $json = json_decode($resp, true);
                if (is_array($json) && ($json[0]['Status'] ?? '') === 'Success') {
                    $po = $json[0]['PostOffice'][0] ?? null;
                    if ($po) {
                        $this->city = (string)($po['District'] ?? $po['Block'] ?? $po['Name'] ?? $this->city);
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore; user can type city
        }
    }

    public function fillLatLngFromAddress(): void
    {
        $query = trim($this->address_line1 . ' ' . $this->address_line2 . ' ' . $this->city . ' ' . $this->pincode . ' India');
        if ($query === '') return;

        try {
            $q   = urlencode($query);
            $url = "https://nominatim.openstreetmap.org/search?format=json&q={$q}&limit=1";
            $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => "User-Agent: DayliSignup/1.0\r\n"]]);
            $res = @file_get_contents($url, false, $ctx);
            $arr = $res ? json_decode($res, true) : null;

            if (is_array($arr) && !empty($arr[0])) {
                $this->lat  = (float)$arr[0]['lat'];
                $this->lng  = (float)$arr[0]['lon'];
                $this->zone = $this->zone ?: ($this->mapZoneFromGeo($this->lat, $this->lng, $this->pincode) ?? '');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function setBrowserLocation($lat, $lng): void
    {
        if (!is_null($lat) && !is_null($lng)) {
            $this->lat  = (float)$lat;
            $this->lng  = (float)$lng;
            $this->zone = $this->zone ?: ($this->mapZoneFromGeo($this->lat, $this->lng, $this->pincode) ?? '');
        }
    }

    public function mapZoneFromGeo($lat, $lng, $pincode): ?string
    {
        return null;
    }

    public function mount(?int $userId = null): void
    {
        $this->userId = $userId ?: session('signup_user_id');

        if ($this->userId) {
            $this->user = User::find($this->userId);
            if ($this->user) {
                $this->first_name = $this->user->first_name ?? $this->first_name;
                $this->last_name  = $this->user->last_name  ?? $this->last_name;
                $this->email      = $this->user->email      ?? $this->email;
                $this->phone      = $this->user->phone      ?? $this->phone;
            }
        }
    }

    #[On('profile:submit')]
    public function onSubmit(): void
    {
        $this->save();
    }

    /** Convert $this->zone to a zone_id (int|null) if possible. */
    protected function zoneIdOrNull(): ?int
    {
        // If you later add a Zone model lookup by name/code, do it here.
        return is_numeric($this->zone) ? (int)$this->zone : null;
    }

    public function save(): void
    {
        $this->validate();

        $user = $this->user ?? ($this->userId ? User::find($this->userId) : null);
        if (!$user) {
            $this->addError('user', 'Session expired. Please sign in again.');
            return;
        }

        DB::transaction(function () use ($user) {
            // 1) update user core fields
            $user->first_name = $this->first_name;
            $user->last_name  = $this->last_name;
            $user->phone      = $this->phone;
            $user->email      = $this->email;
            $user->save();

            // 2) upsert default address for this user
            Address::updateOrCreate(
                [
                    'addressable_type' => User::class,
                    'addressable_id'   => $user->id,
                    'is_default'       => true,
                ],
                [
                    // optional label if you have this column
                    //'label'   => 'Primary',
                    'line1'   => $this->address_line1,
                    'line2'   => $this->address_line2 ?: null,
                    'city'    => $this->city,
                    'state'   => null,          // set if you capture it
                    'country' => null,          // set if you capture it
                    'pincode' => $this->pincode,
                    'lat'     => $this->lat,
                    'lng'     => $this->lng,
                    'nagar'   => $this->nagar ?: null,
                    'zone_id' => $this->zoneIdOrNull(),
                ]
            );
        });

        // 3) tell the wizard (if needed)
        $this->dispatch('profileSaved');

        // 4) log in the user (finish the flow), clear temp
        Auth::login($user);
        session()->forget('signup_user_id');

        // 5) redirect to dashboard (Livewire v3 navigate)
        $this->redirectRoute('overview', navigate: true);
        // If using Livewire v2: return redirect()->route('dashboard.overview');
    }

    public function render()
    {
        return view('livewire.signup.vendor-profile-details');
    }
}
