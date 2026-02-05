<div>
    <form wire:submit.prevent="submit">
        <div>
            <label>First Name</label>
            <input type="text" wire:model="first_name">
        </div>
        <div>
            <label>Phone</label>
            <input type="text" wire:model="phone">
        </div>
        <!-- Add other fields similarly -->
        <input type="hidden" wire:model="collected_lat">
        <input type="hidden" wire:model="collected_lng">
        <button type="submit">Save Lead</button>
    </form>

    <script>
        document.addEventListener('livewire:load', function () {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    @this.set('collected_lat', position.coords.latitude);
                    @this.set('collected_lng', position.coords.longitude);
                });
            }
        });
    </script>
</div>
