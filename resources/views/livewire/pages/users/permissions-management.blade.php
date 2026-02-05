<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div>
                @if ($showSuccessNotification)
                <div wire:model.live="showSuccessNotification" class="mt-3 alert alert-primary alert-dismissible fade show"
                    role="alert">
                    <span class="alert-icon text-white"><i class="ni ni-like-2"></i></span>
                    <span class="alert-text text-white">{{ __('The permission has been successfully deleted.') }}</span>
                    <button wire:click="$set('showSuccessNotification', false)" type="button" class="btn-close"
                        data-bs-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @endif
            </div>
            <div>
                @if ($showFailureNotification)
                <div wire:model.live="showFailureNotification" class="mt-3 alert alert-danger alert-dismissible fade show"
                    role="alert">
                    <span class="alert-text text-white"> {{ __('The permission can not be deleted.') }}</span>
                    <button wire:click="$set('showFailureNotification', false)" type="button" class="btn-close"
                        data-bs-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @endif
            </div>
            <div>
                @if (session('success'))
                <div class="mt-3 alert alert-primary alert-dismissible fade show" role="alert">
                    <span class="alert-icon text-white"><i class="ni ni-like-2"></i></span>
                    <span class="alert-text text-white">{{ session('success') }}</span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @endif
            </div>
            <div class="card">
                <div class="d-flex flex-column mx-3 mt-3">
                    <div class="d-flex flex-row justify-content-between">
                        <div>
                            <h5 class="mb-0">{{ __('All Permissions') }}</h5>
                        </div>
                        @if (auth()->user()->can('create', App\Models\Permission::class))
                        <a href="{{ route('new-permission') }}" class="btn bg-gradient-primary btn-sm mb-0"
                            type="button">+&nbsp; {{ __('New Permission') }}</a>
                        @endif
                    </div>
                    <div class="d-flex flex-row justify-content-between">
                        <div class="d-flex mt-3 align-items-center justify-content-center">
                            <p class="text-secondary pt-2">{{ __('Show') }}&nbsp;&nbsp;</p>
                            <select wire:model.live="perPage" class="form-control" id="entries">
                                <option value="5">{{ __('5') }}</option>
                                <option selected value="10">{{ __('10') }}</option>
                                <option value="15">{{ __('15') }}</option>
                                <option value="20">{{ __('20') }}</option>
                            </select>
                            <p class="text-secondary pt-2">&nbsp;&nbsp;{{ __('entries') }}</p>
                        </div>
                        <div class="mt-3 ">
                            <input wire:model.live="search" type="text" class="form-control" placeholder="Search...">
                        </div>
                    </div>
                </div>
                <x-table>
                    <x-slot name="head">
                        <x-table.heading sortable wire:click="sortBy('id')" :direction="$sortField === 'id' ? $sortDirection : null">{{ __('ID') }}
                        </x-table.heading>
                        <x-table.heading sortable wire:click="sortBy('name')" :direction="$sortField === 'name' ? $sortDirection : null">
                            {{ __('Name') }}
                        </x-table.heading>
                        <x-table.heading>Photo</x-table.heading>
                        <x-table.heading sortable wire:click="sortBy('description')" :direction="$sortField === 'description' ? $sortDirection : null">
                            {{ __('Description') }}
                        </x-table.heading>
                        <x-table.heading sortable wire:click="sortBy('status')" :direction="$sortField === 'status' ? $sortDirection : null">
                            {{ __('Status') }}
                        </x-table.heading>
                        <x-table.heading sortable wire:click="sortBy('created_at')" :direction="$sortField === 'created_at' ? $sortDirection : null">
                            {{ __('Creation Date') }}
                        </x-table.heading>
                        @can('manage-permissions', App\User::class)
                        <x-table.heading>{{ __('Action') }}</x-table.heading>
                        @endcan
                    </x-slot>

                    <x-slot name="body">
                        @foreach ($permissions as $permission)
                        <x-table.row wire:key="row-{{ $permission->id }}">
                            <x-table.cell>{{ $permission->id }}</x-table.cell>
                            <x-table.cell>{{ $permission->name }}</x-table.cell>
                            <x-table.cell>{{ $permission->description }}</x-table.cell>
                            <x-table.cell>{{ $permission->status }}</x-table.cell>

                            <x-table.cell>{{ $permission->created_at }}</x-table.cell>
                            <x-table.cell>
                                @can('manage-permissions', auth()->user())
                                @can('update', $permission)
                                <a href="{{ route('edit-permission', ['id' => $permission->id]) }}" class="mx-3"
                                    data-bs-toggle="tooltip" data-bs-original-title="Edit Permission">
                                    <i class="fas fa-user-edit text-secondary"></i>
                                </a>
                                @endcan
                                @can('delete', $permission)
                                <span>
                                    <i onclick="confirm('Are you sure you want to remove the permission?') || event.stopImmediatePropagation()"
                                        wire:click="delete({{ $permission->id }})"
                                        class="cursor-pointer fas fa-trash text-secondary"></i>
                                </span>
                                @endcan
                                @endcan
                            </x-table.cell>
                        </x-table.row>
                        @endforeach
                    </x-slot>
                </x-table>

            </div>
        </div>
    </div>
</div>
