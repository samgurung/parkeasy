<?php

namespace App\Livewire\Admin;

use App\Models\ParkingLot;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class VehicleManager extends Component
{
    /**
     * How many vehicles the page shows before a search is typed. The registry grows without
     * bound, so the landing view stays a fixed size and search is the way to reach a
     * specific vehicle.
     */
    private const RECENT_LIMIT = 3;

    /**
     * Ceiling on search results. A broad term ("1", "a") can match most of the registry,
     * and rendering every match would recreate the unbounded list this screen is avoiding.
     */
    private const SEARCH_LIMIT = 25;

    public string $rfidId = '';

    public string $vehicleNumber = '';

    public string $vehicleType = ParkingLot::VEHICLE_FOUR_WHEELER;

    public string $driverName = '';

    public string $mobileNumber = '';

    /**
     * Which lot this registration is attributed to. A lot admin only ever sees their own
     * lots here; a super admin may leave it blank for a card with no single origin.
     */
    public string $lotId = '';

    public ?int $editingId = null;

    public string $editRfidId = '';

    public string $editVehicleNumber = '';

    public string $editVehicleType = ParkingLot::VEHICLE_FOUR_WHEELER;

    public string $editDriverName = '';

    public string $editMobileNumber = '';

    public ?int $deletingId = null;

    public ?string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Vehicle::class);

        // A lot admin is bound to their lot, so that is the sensible default attribution
        // rather than making them pick it on every card.
        $lotIds = $this->administeredLotIds();

        if (count($lotIds) === 1) {
            $this->lotId = (string) $lotIds[0];
        }
    }

    /**
     * Lots this user may attribute a registration to. A super admin administers every lot,
     * so their list is the whole portfolio.
     *
     * @return array<int, int>
     */
    private function administeredLotIds(): array
    {
        $user = auth()->user();

        return $user->administeredLotIds() ?? ParkingLot::pluck('id')->all();
    }

    public function render(): View
    {
        $search = trim($this->search);

        // Unsearched, the page answers "what did we just add?" - and for a lot admin that
        // means at their own lot, which is the only question they can act on. Search stays
        // site-wide below, because a card bound at another lot still has to be findable
        // when its driver turns up at the wrong gate.
        $vehicles = $search === ''
            ? $this->recentQuery()->get()
            : $this->searchQuery($search)->orderBy('vehicle_number')->limit(self::SEARCH_LIMIT)->get();

        $scoped = ! auth()->user()->isSuperAdmin();

        return view('livewire.admin.vehicle-manager', [
            'vehicles' => $vehicles,
            'lots' => ParkingLot::query()->whereIn('id', $this->administeredLotIds())->orderBy('lot_number')->get(),
            'searching' => $search !== '',
            'search' => $search,
            // Counts behind the caption. The recent list is lot-scoped for a lot admin, so
            // it counts within that scope; the search total stays site-wide, which is what
            // makes a search that finds another lot's card legible as "not one of yours".
            'recentTotal' => $scoped
                ? Vehicle::whereIn('registered_at_lot_id', $this->administeredLotIds())->count()
                : Vehicle::count(),
            'recentScopeLabel' => $scoped ? ' at your lot' : '',
            'totalVehicles' => Vehicle::count(),
            'matchCount' => $search === '' ? 0 : $this->searchQuery($search)->count(),
            // A lot admin may bind a card on arrival but may not correct or remove one:
            // both change what every other gate believes. The methods above enforce it;
            // these only decide which controls render.
            'canEdit' => auth()->user()->can('update', Vehicle::class),
            'canDelete' => auth()->user()->can('delete', Vehicle::class),
        ])->layout('components.layouts.app', ['title' => 'Admin – Vehicles | ParkEasy']);
    }

    /**
     * Newest first by creation, so the list answers the question an operator has right
     * after registering a card.
     */
    protected function recentQuery(): Builder
    {
        $query = Vehicle::query()->withCount('entries')->with('registeredAtLot');

        // A super admin's reach is every lot, so for them this stays a site-wide feed.
        if (! auth()->user()->isSuperAdmin()) {
            $query->whereIn('registered_at_lot_id', $this->administeredLotIds());
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->limit(self::RECENT_LIMIT);
    }

    /**
     * Cards, registrations, drivers and mobile numbers are all things an operator might
     * have in front of them at the gate, so a single term matches any of them.
     *
     * Deliberately not lot-scoped: see recentQuery().
     */
    protected function searchQuery(string $search): Builder
    {
        $term = '%'.$search.'%';

        return Vehicle::query()
            ->with('registeredAtLot')
            ->where(function (Builder $query) use ($term) {
                $query->where('rfid_id', 'like', $term)
                    ->orWhere('vehicle_number', 'like', $term)
                    ->orWhere('driver_name', 'like', $term)
                    ->orWhere('mobile_number', 'like', $term);
            });
    }

    public function add(): void
    {
        Gate::authorize('create', Vehicle::class);

        $validated = $this->validate([
            'rfidId' => ['required', 'string', 'max:255', 'unique:vehicles,rfid_id'],
            // The registry is site-wide, so the registration is unique across every lot.
            'vehicleNumber' => ['required', 'string', 'max:255', 'unique:vehicles,vehicle_number'],
            'vehicleType' => ['required', Rule::in([ParkingLot::VEHICLE_TWO_WHEELER, ParkingLot::VEHICLE_FOUR_WHEELER])],
            'driverName' => ['required', 'string', 'max:255'],
            'mobileNumber' => ['required', 'string', 'size:10'],
            'lotId' => ['nullable', 'integer', Rule::exists('parking_lots', 'id')],
        ]);

        // A lot admin must not be able to attribute a registration to a lot they do not
        // administer by posting an id, even though the dropdown would never offer it.
        $lotId = $this->lotId === '' ? null : (int) $this->lotId;

        if ($lotId !== null && ! in_array($lotId, $this->administeredLotIds(), true)) {
            abort(403, 'You can only register cards against the lots you administer.');
        }

        Vehicle::create([
            'rfid_id' => strtoupper(trim($validated['rfidId'])),
            'vehicle_number' => strtoupper(trim($validated['vehicleNumber'])),
            'vehicle_type' => $validated['vehicleType'],
            'driver_name' => trim($validated['driverName']),
            'mobile_number' => $validated['mobileNumber'],
            'registered_by_user_id' => auth()->id(),
            'registered_at_lot_id' => $lotId,
        ]);

        $this->reset('rfidId', 'vehicleNumber', 'vehicleType', 'driverName', 'mobileNumber');

        // Keep the attribution sticky: re-registering the next card at the same lot should
        // not mean choosing the lot again.
        $this->lotId = (string) ($lotId ?? '');
    }

    public function edit(int $id): void
    {
        Gate::authorize('update', Vehicle::class);
        $vehicle = Vehicle::findOrFail($id);

        $this->editingId = $vehicle->id;
        $this->editRfidId = $vehicle->rfid_id;
        $this->editVehicleNumber = $vehicle->vehicle_number;
        $this->editVehicleType = $vehicle->vehicle_type;
        $this->editDriverName = $vehicle->driver_name;
        $this->editMobileNumber = $vehicle->mobile_number;
    }

    public function save(): void
    {
        Gate::authorize('update', Vehicle::class);

        $validated = $this->validate([
            'editRfidId' => [
                'required', 'string', 'max:255',
                Rule::unique('vehicles', 'rfid_id')->ignore($this->editingId),
            ],
            'editVehicleNumber' => [
                'required', 'string', 'max:255',
                // Ignored on the record being edited, or saving without touching the
                // registration would collide with itself.
                Rule::unique('vehicles', 'vehicle_number')->ignore($this->editingId),
            ],
            'editVehicleType' => ['required', Rule::in([ParkingLot::VEHICLE_TWO_WHEELER, ParkingLot::VEHICLE_FOUR_WHEELER])],
            'editDriverName' => ['required', 'string', 'max:255'],
            'editMobileNumber' => ['required', 'string', 'size:10'],
        ]);

        $vehicle = Vehicle::findOrFail($this->editingId);

        $rfid = strtoupper(trim($validated['editRfidId']));

        $vehicle->update([
            'rfid_id' => $rfid,
            'vehicle_number' => strtoupper(trim($validated['editVehicleNumber'])),
            'vehicle_type' => $validated['editVehicleType'],
            'driver_name' => trim($validated['editDriverName']),
            'mobile_number' => $validated['editMobileNumber'],
        ]);

        // Past visits recorded the card code, so re-keying a card has to follow through or
        // those visits can no longer be matched to the vehicle they belong to.
        if ($vehicle->wasChanged('rfid_id')) {
            $vehicle->entries()->update(['rfid_id' => $rfid]);
        }

        $this->cancel();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'editRfidId', 'editVehicleNumber', 'editVehicleType', 'editDriverName', 'editMobileNumber');
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('delete', Vehicle::class);
        $this->deletingId = $id;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    public function delete(): void
    {
        Gate::authorize('delete', Vehicle::class);
        $vehicle = Vehicle::findOrFail($this->deletingId);

        // Keep the visit history but detach it, so closed entries remain auditable while
        // the card becomes free to register against a different vehicle.
        $vehicle->entries()->update(['vehicle_id' => null]);

        $vehicle->delete();

        $this->deletingId = null;
    }
}
