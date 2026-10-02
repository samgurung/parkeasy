<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ScopesToAdministeredLots;
use App\Models\ParkingLot;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class LotManager extends Component
{
    use ScopesToAdministeredLots, WithPagination;

    /**
     * A lot admin may read their own lots - the nav and the pickers on other screens need
     * them - but may not change site-wide configuration, so the whole component is a
     * read-only list for that role.
     */
    public function mount(): void
    {
        Gate::authorize('viewAny', ParkingLot::class);
    }

    #[Validate(['required', 'string', 'max:255'])]
    public string $name = '';

    #[Validate(['nullable', 'string', 'max:255'])]
    public string $address = '';

    #[Validate(['required', 'numeric', 'min:0'])]
    public string $rateTwoWheeler = '10';

    #[Validate(['required', 'numeric', 'min:0'])]
    public string $rateFourWheeler = '20';

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $editName = '';

    public string $editAddress = '';

    public string $editRateTwoWheeler = '';

    public string $editRateFourWheeler = '';

    public function add(): void
    {
        Gate::authorize('create', ParkingLot::class);
        $this->validate();

        ParkingLot::create([
            'name' => $this->name,
            'lot_number' => ParkingLot::nextLotNumber(),
            'address' => $this->address ?: null,
            'rate_two_wheeler' => $this->rateTwoWheeler,
            'rate_four_wheeler' => $this->rateFourWheeler,
        ]);

        $this->reset('name', 'address');
    }

    public function edit(int $id): void
    {
        Gate::authorize('update', ParkingLot::class);
        $lot = ParkingLot::findOrFail($id);

        $this->editingId = $lot->id;
        $this->editName = $lot->name;
        $this->editAddress = (string) $lot->address;
        $this->editRateTwoWheeler = (string) $lot->rate_two_wheeler;
        $this->editRateFourWheeler = (string) $lot->rate_four_wheeler;
    }

    public function save(): void
    {
        Gate::authorize('update', ParkingLot::class);
        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editAddress' => ['nullable', 'string', 'max:255'],
            'editRateTwoWheeler' => ['required', 'numeric', 'min:0'],
            'editRateFourWheeler' => ['required', 'numeric', 'min:0'],
        ]);

        ParkingLot::whereKey($this->editingId)->update([
            'name' => $this->editName,
            'address' => $this->editAddress ?: null,
            'rate_two_wheeler' => $this->editRateTwoWheeler,
            'rate_four_wheeler' => $this->editRateFourWheeler,
        ]);

        $this->cancel();
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->editName = '';
        $this->editAddress = '';
        $this->editRateTwoWheeler = '';
        $this->editRateFourWheeler = '';
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('delete', ParkingLot::class);
        $this->deletingId = $id;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    public function delete(): void
    {
        Gate::authorize('delete', ParkingLot::class);
        ParkingLot::whereKey($this->deletingId)->delete();
        $this->deletingId = null;
    }

    public function render()
    {
        return view('livewire.admin.lot-manager', [
            'lots' => ParkingLot::withCount('floors', 'slots')
                ->administeredBy($this->user())
                ->orderBy('lot_number')
                ->get(),
            // Drives whether the create/edit/delete controls render at all. The policy
            // checks in the methods above are the real enforcement; this only avoids
            // showing a lot admin buttons that would only ever 403.
            'canManageLots' => $this->user()->can('create', ParkingLot::class),
        ])->layout('components.layouts.app', ['title' => 'Parking Lots | ParkEasy']);
    }
}
