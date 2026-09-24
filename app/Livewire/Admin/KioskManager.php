<?php

namespace App\Livewire\Admin;

use App\Models\Kiosk;
use App\Models\ParkingLot;
use Illuminate\View\View;
use Livewire\Component;

class KioskManager extends Component
{
    public string $name = '';

    public ?int $lotId = null;

    public ?int $editingId = null;

    public string $editName = '';

    public ?int $editLotId = null;

    public ?int $deletingId = null;

    public function render(): View
    {
        return view('livewire.admin.kiosk-manager', [
            'kiosks' => Kiosk::with([
                'parkingLot' => fn ($q) => $q->withCount(['floors', 'slots']),
            ])->orderBy('name')->get(),
            'lots' => ParkingLot::orderBy('lot_number')->get(),
        ])->layout('components.layouts.app', ['title' => 'Admin – Kiosks | ParkEasy']);
    }

    public function add(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'lotId' => ['required', 'exists:parking_lots,id'],
        ]);

        Kiosk::create([
            'name' => trim($validated['name']),
            'key' => Kiosk::makeKey($validated['name']),
            'parking_lot_id' => $validated['lotId'],
        ]);

        $this->reset('name', 'lotId');
    }

    public function edit(int $id): void
    {
        $kiosk = Kiosk::findOrFail($id);

        $this->editingId = $kiosk->id;
        $this->editName = $kiosk->name;
        $this->editLotId = $kiosk->parking_lot_id;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editLotId' => ['nullable', 'exists:parking_lots,id'],
        ]);

        Kiosk::whereKey($this->editingId)->update([
            'name' => trim($validated['editName']),
            'parking_lot_id' => $this->editLotId ?: null,
        ]);

        $this->cancel();
    }

    public function delink(int $id): void
    {
        Kiosk::whereKey($id)->update(['parking_lot_id' => null]);
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->editName = '';
        $this->editLotId = null;
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
        Kiosk::whereKey($this->deletingId)->delete();
        $this->deletingId = null;
    }
}
