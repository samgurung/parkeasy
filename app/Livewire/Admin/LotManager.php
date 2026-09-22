<?php

namespace App\Livewire\Admin;

use App\Models\ParkingLot;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class LotManager extends Component
{
    use WithPagination;

    #[Validate(['required', 'string', 'max:255'])]
    public string $name = '';

    #[Validate(['nullable', 'string', 'max:255'])]
    public string $address = '';

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $editName = '';

    public string $editAddress = '';

    public function add(): void
    {
        $this->validate();

        ParkingLot::create([
            'name'       => $this->name,
            'lot_number' => ParkingLot::nextLotNumber(),
            'address'    => $this->address ?: null,
        ]);

        $this->reset('name', 'address');
    }

    public function edit(int $id): void
    {
        $lot = ParkingLot::findOrFail($id);

        $this->editingId   = $lot->id;
        $this->editName    = $lot->name;
        $this->editAddress = (string) $lot->address;
    }

    public function save(): void
    {
        $this->validate([
            'editName'    => ['required', 'string', 'max:255'],
            'editAddress' => ['nullable', 'string', 'max:255'],
        ]);

        ParkingLot::whereKey($this->editingId)->update([
            'name'    => $this->editName,
            'address' => $this->editAddress ?: null,
        ]);

        $this->cancel();
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->editName = '';
        $this->editAddress = '';
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    public function delete(): void
    {
        ParkingLot::whereKey($this->deletingId)->delete();
        $this->deletingId = null;
    }

    public function render()
    {
        return view('livewire.admin.lot-manager', [
            'lots' => ParkingLot::withCount('floors')->orderBy('lot_number')->get(),
        ]);
    }
}
