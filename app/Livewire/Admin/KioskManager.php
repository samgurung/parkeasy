<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ScopesToAdministeredLots;
use App\Models\Kiosk;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class KioskManager extends Component
{
    use ScopesToAdministeredLots;

    public function mount(): void
    {
        Gate::authorize('viewAny', Kiosk::class);
    }

    public string $name = '';

    public ?int $lotId = null;

    public ?string $type = null;

    public ?int $editingId = null;

    public string $editName = '';

    public ?int $editLotId = null;

    public ?string $editType = null;

    public ?int $deletingId = null;

    public function render(): View
    {
        return view('livewire.admin.kiosk-manager', [
            // Unattached kiosks belong to nobody, so a lot admin does not see them; only a
            // super admin does, matching KioskPolicy. Super admins get no clause at all.
            'kiosks' => Kiosk::with([
                'parkingLot' => fn ($q) => $q->withCount(['floors', 'slots']),
            ])
                ->when(! $this->isSuperAdmin(), fn ($q) => $q->whereIn(
                    'parking_lot_id',
                    $this->administeredLots()->select('parking_lots.id')
                ))
                ->orderBy('name')
                ->get(),
            'lots' => $this->administeredLots()->get(),
            // Detaching a kiosk makes it invisible to every lot admin, so the control is
            // offered to the super admin only.
            'canDetach' => $this->isSuperAdmin(),
        ])->layout('components.layouts.app', ['title' => 'Admin – Kiosks | ParkEasy']);
    }

    public function add(): void
    {
        Gate::authorize('create', Kiosk::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'lotId' => ['required', 'exists:parking_lots,id'],
            'type' => ['required', Rule::in([Kiosk::TYPE_ENTRY, Kiosk::TYPE_EXIT])],
        ]);

        abort_unless(
            $lotId = $this->authorizedLotId($validated['lotId']),
            403,
            'You can only attach kiosks to the lots you administer.'
        );

        Kiosk::create([
            'name' => trim($validated['name']),
            'key' => Kiosk::makeKey($validated['name']),
            'type' => $validated['type'],
            'parking_lot_id' => $lotId,
        ]);

        $this->reset('name', 'lotId', 'type');
    }

    public function edit(int $id): void
    {
        $kiosk = Kiosk::findOrFail($id);
        Gate::authorize('update', $kiosk);

        $this->editingId = $kiosk->id;
        $this->editName = $kiosk->name;
        $this->editLotId = $kiosk->parking_lot_id;
        $this->editType = $kiosk->type;
    }

    public function save(): void
    {
        $kiosk = Kiosk::findOrFail($this->editingId);
        Gate::authorize('update', $kiosk);

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editLotId' => ['nullable', 'exists:parking_lots,id'],
            'editType' => ['required', Rule::in([Kiosk::TYPE_ENTRY, Kiosk::TYPE_EXIT])],
        ]);

        // Detaching is a super-admin move: it turns a kiosk this admin can reach into one
        // they cannot, so the null case is checked against the same rule as a real lot.
        $newLotId = $validated['editLotId']
            ? $this->authorizedLotId($validated['editLotId'])
            : ($this->isSuperAdmin() ? null : false);

        abort_if($newLotId === false, 403, 'You can only move kiosks between the lots you administer.');

        $kiosk->update([
            'name' => trim($validated['editName']),
            'type' => $validated['editType'],
            'parking_lot_id' => $newLotId,
        ]);

        $this->cancel();
    }

    public function delink(int $id): void
    {
        $kiosk = Kiosk::findOrFail($id);
        Gate::authorize('update', $kiosk);
        Gate::authorize('create', Kiosk::class);

        // Detaching always needs the broader capability, because the result is a kiosk
        // that no lot admin can see.
        abort_unless($this->isSuperAdmin(), 403, 'Only a super admin can detach a kiosk from its lot.');

        $kiosk->update(['parking_lot_id' => null]);
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->editName = '';
        $this->editLotId = null;
        $this->editType = null;
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
        $kiosk = Kiosk::findOrFail($this->deletingId);
        Gate::authorize('delete', $kiosk);
        $kiosk->delete();
        $this->deletingId = null;
    }
}
