<div class="container py-4">
    <h2 class="text-xl font-semibold mb-4">Edit Delivery #{{ $deliveryId }}</h2>

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
                <label>Delivery Boy</label>
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
                <label>Quantity</label>
                <input type="number" wire:model="product_count" class="form-input w-full" />
                @error('product_count') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        <div>
            <label>Status</label>
            <select wire:model="status" class="form-select w-full">
                <option value="pending_approval">Pending Approval</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <div class="pt-4">
            <button type="submit" class="btn btn-primary">Update Delivery</button>
        </div>
    </form>
</div>
