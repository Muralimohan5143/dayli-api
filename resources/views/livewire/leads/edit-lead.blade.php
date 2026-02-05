<div>
    <form wire:submit.prevent="update">
        <div>
            <label>First Name</label>
            <input type="text" wire:model="first_name">
        </div>
        <div>
            <label>Phone</label>
            <input type="text" wire:model="phone">
        </div>
        <!-- Add other fields similarly -->
        <button type="submit">Update Lead</button>
    </form>
</div>
