<div class="container py-4">
    <h2 class="text-xl font-semibold mb-4">Create New Sub Change Request</h2>

    <form wire:submit.prevent="submit" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label>Customer</label>
                <select wire:model="for_user_id" class="form-select w-full">
                    <option value="">Select</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                @error('for_user_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label>By User (Staff)</label>
                <select wire:model="by_user_id" class="form-select w-full">
                    <option value="">Select</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                @error('by_user_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        <div>
            <label>Vendor (Optional)</label>
            <select wire:model="from_id" class="form-select w-full">
                <option value="">None</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label>Product</label>
                <select wire:model="product_id" class="form-select w-full">
                    <option value="">Select</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->title }}</option>
                    @endforeach
                </select>
                @error('product_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label>Product Count</label>
                <input type="number" wire:model="product_count" class="form-input w-full" />
                @error('product_count') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label>Frequency Type</label>
                <select wire:model="frequency_type" class="form-select w-full">
                    <option value="daily">Daily</option>
                    <option value="alternate_days">Alternate Days</option>
                    <option value="weekdays">Weekdays</option>
                    <option value="weekends">Weekends</option>
                    <option value="sat">Saturday Only</option>
                    <option value="sun">Sunday Only</option>
                    <option value="custom">Custom</option>
                    <option value="on_demand">On Demand</option>
                </select>
            </div>
            <div>
                <label>Custom Format (if selected)</label>
                <input type="text" wire:model="custom_frequency_format" placeholder="e.g. w1=2&w7=2" class="form-input w-full" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label>Start Date</label>
                <input type="date" wire:model="start_date" class="form-input w-full" />
            </div>
            <div>
                <label>End Date</label>
                <input type="date" wire:model="end_date" class="form-input w-full" />
            </div>
        </div>

        <div>
            <label>Change Reason</label>
            <select wire:model="change_reason" class="form-select w-full">
                <option value="self_service">Self Service</option>
                <option value="user-error">User Error</option>
                <option value="staff-error">Staff Error</option>
            </select>
        </div>

        <div class="pt-4">
            <button type="submit" class="btn btn-primary">Save Request</button>
        </div>
    </form>
</div>
