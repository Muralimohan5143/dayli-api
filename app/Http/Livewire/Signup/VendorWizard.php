<?php

namespace App\Http\Livewire\VendorSignup;

use Livewire\Component;

class Wizard extends Component
{
    /** Current step: 1 = Signin (embedded), 2 = Contract, 3 = Profile */
    public int $step = 1;

    /* ---------------------------
     * STEP 2: Contract (Vendor/Workman)
     * --------------------------- */
    /** '' | 'vendor' | 'workman' */
    public string $primaryType = '';

    /** Selected subscription/service key, e.g. milk_dairy | vegetables | ... */
    public ?string $subscriptionType = null;

    /**
     * For vendor types that have sub-types, we bind selections per type:
     *   ['milk_dairy' => ['milk','curd'], 'vegetables' => ['leafy_veg', ...]]
     */
    public array $subtypesSelectedMap = [];

    /**
     * Pricing payload keyed by sub-type slug:
     *   ['milk' => ['discount_pct'=>..,'discount_amt'=>..,'cost'=>..], ...]
     */
    public array $pricing = [];

    /** Optional: MRP catalog you may preload (slug => mrp number) */
    public array $mrpCatalog = [];

    /* ---------------------------
     * STEP 3: Profile
     * --------------------------- */
    public string $first_name = '';
    public string $last_name  = '';
    public string $phone      = '';
    public string $email      = '';
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city       = '';
    public string $pincode    = '';
    public ?float $lat        = null;
    public ?float $lng        = null;
    public string $nagar      = '';
    public string $zone       = '';

    /* ---------------------------
     * Lifecycle
     * --------------------------- */
    public function mount(): void
    {
        // Allow step/type to come from query (e.g. /vendor-signup?step=2&type=milk)
        $this->step = max(1, min((int) request('step', $this->step), 3));

        if (!$this->subscriptionType && request()->filled('type')) {
            $this->subscriptionType = (string) request('type');
        }

        // If you want to preload MRP values, do it here:
        // $this->mrpCatalog = ['milk' => 50, 'curd' => 40, ...];
    }

    /* ---------------------------
     * STEP 2 handlers
     * --------------------------- */
    public function updatedPrimaryType(): void
    {
        // Reset dependent fields when switching role
        $this->subscriptionType     = null;
        $this->subtypesSelectedMap  = [];
        $this->pricing              = [];
    }

    public function updatedSubscriptionType(): void
    {
        if ($this->subscriptionType && !isset($this->subtypesSelectedMap[$this->subscriptionType])) {
            $this->subtypesSelectedMap[$this->subscriptionType] = [];
        }
    }

    public function continueFromType(): void
    {
        $this->validate([
            'primaryType' => ['required', 'in:vendor,workman'],
        ]);

        // Allowed keys (mirror your blades)
        $vendorKeys = [
            'bakery_snacks',
            'beverages',
            'chaats_quick_snacks',
            'fish_seafood',
            'flowers',
            'fruits',
            'groceries',
            'health_packs',
            'meat',
            'milk_dairy',
            'puja_samagri',
            'services',
            'sweets_confectionery',
            'vegetables',
        ];
        $workmanKeys = [
            'building_painter',
            'carpenter',
            'cleaning',
            'cooking',
            'electrical',
            'gardening',
            'home_security',
            'plumbing',
        ];

        if ($this->primaryType === 'vendor') {
            $this->validate([
                'subscriptionType' => ['required', 'in:' . implode(',', $vendorKeys)],
            ]);
        } else {
            $this->validate([
                'subscriptionType' => ['required', 'in:' . implode(',', $workmanKeys)],
            ]);
        }

        // If you want to enforce at least one sub-type for certain vendor categories:
        // $needsSubtype = in_array($this->subscriptionType, ['milk_dairy','vegetables','fruits'], true);
        // if ($needsSubtype && count($this->subtypesSelectedMap[$this->subscriptionType] ?? []) === 0) {
        //     $this->addError('subtypesSelectedMap', 'Please choose at least one sub-type.');
        //     return;
        // }

        $this->step = 3;
    }

    /* ---------------------------
     * STEP 3 validation + submit
     * --------------------------- */
    public function rulesProfile(): array
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
        ];
    }

    /** Optional live validation for key profile fields */
    public function updated($prop): void
    {
        $live = [
            'first_name',
            'last_name',
            'phone',
            'email',
            'address_line1',
            'pincode',
            'lat',
            'lng',
        ];
        if (in_array($prop, $live, true)) {
            $this->validateOnly($prop, $this->rulesProfile());
        }
    }

    public function submitWizard()
    {
        $this->validate($this->rulesProfile());

        // TODO: Persist contract choices ($primaryType, $subscriptionType, $subtypesSelectedMap, $pricing)
        //       and profile fields (address & geo). Map zone if needed.
        // Example: create onboarding record, or attach to the authed user.

        session()->flash('success', 'Onboarding saved successfully.');
        // Optionally: return redirect()->route('vendor.signup.success');
    }

    /* ---------------------------
     * Helpers callable from JS
     * --------------------------- */
    public function mapZoneFromGeo($lat, $lng, $pincode): ?string
    {
        // Implement server-side zone mapping if client couldn’t infer it.
        return null;
    }

    /* ---------------------------
     * View
     * --------------------------- */
    public function render()
    {
        // This is the main wizard blade that:
        // - shows the stepper
        // - embeds auth.signin for step=1 (with redirectUrl to ?step=2)
        // - includes vendor-<type> partial for step=2
        // - includes profile partial for step=3
        return view('livewire.vendor-signup.index');
    }
}
