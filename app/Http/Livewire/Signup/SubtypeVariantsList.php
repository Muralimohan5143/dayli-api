<?php

namespace App\Http\Livewire\Signup;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * SubtypeVariantsList
 *
 * Acts as a wrapper around ZoneVariantsList.
 * This way, your older blades that call
 * <livewire:signup.subtype-variants-list ... />
 * still work, but internally everything
 * is handled by ZoneVariantsList.
 */
class SubtypeVariantsList extends Component
{
    /** Example: 'milk_dairy' */
    public string $category = '';

    /** Example: 'Milk', 'Curd', 'Paneer' */
    public string $subtype = '';

    /** Zone ID context */
    public int $zoneId = 1;

    /** Optional vendor ID */
    public ?int $vendorId = null;

    /**
     * Mount hook: initialize all props
     */
    public function mount(
        string $category = '',
        string $subtype = '',
        int $zoneId = 1,
        ?int $vendorId = null
    ): void {
        $this->category = $category;
        $this->subtype  = $subtype;
        $this->zoneId   = $zoneId ?: 1;
        $this->vendorId = $vendorId;
    }

    /**
     * Render: forward props to blade wrapper
     */
    public function render(): View
    {
        return view('livewire.signup.subtype-variants-list', [
            'category' => $this->category,
            'subtype'  => $this->subtype,
            'zoneId'   => $this->zoneId,
            'vendorId' => $this->vendorId,
        ]);
    }
}
