<?php

namespace App\Models;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The role and permission catalogue, in one place so that the seeder, the policies and
 * the navigation cannot drift apart.
 *
 * The shape of the model: there is exactly one super admin, who owns site-wide
 * configuration (the lots themselves) and the corrections that touch other people's
 * records. Lot admins own the operational shape of their own lots - floors, slots and
 * kiosks - and can bind a card to a vehicle at the gate. Operators are the gate staff on
 * the tablet itself: they can run the terminal for a kiosk in their own lot and nothing
 * else, which is why they are a separate role rather than a lot admin with permissions
 * removed. Vehicles are deliberately not lot-scoped, because a card bound anywhere has to
 * be recognised at every gate.
 */
final class Access
{
    public const MANAGE_LOTS = 'lots.manage';

    public const MANAGE_FLOORS = 'floors.manage';

    public const MANAGE_SLOTS = 'slots.manage';

    public const MANAGE_KIOSKS = 'kiosks.manage';

    /**
     * Run a kiosk terminal: reach the gate page for a kiosk in the holder's own lot.
     *
     * Distinct from MANAGE_KIOSKS on purpose. That one is the right to *configure* kiosks -
     * their lot, their gate type, their key - and is granted to lot admins. This one is
     * only the right to stand at a gate and scan, and is granted to nobody else, so the
     * two cannot be confused when the policy reads.
     */
    public const OPERATE_KIOSKS = 'kiosks.operate';

    /** Read the site-wide vehicle registry. Needed at the gate to look up any card. */
    public const VIEW_VEHICLES = 'vehicles.view';

    /** Bind a card to a vehicle on arrival. Deliberately not lot-scoped. */
    public const CREATE_VEHICLES = 'vehicles.create';

    /** Correct an existing registration. Super admin only: it changes other lots' data. */
    public const UPDATE_VEHICLES = 'vehicles.update';

    /** Remove a registration. Super admin only: it can orphan historical visits. */
    public const DELETE_VEHICLES = 'vehicles.delete';

    public const MANAGE_USERS = 'users.manage';

    /**
     * @return array<int, string>
     */
    public static function operatorPermissions(): array
    {
        return [
            self::OPERATE_KIOSKS,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function lotAdminPermissions(): array
    {
        return [
            self::MANAGE_FLOORS,
            self::MANAGE_SLOTS,
            self::MANAGE_KIOSKS,
            self::VIEW_VEHICLES,
            self::CREATE_VEHICLES,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function superAdminPermissions(): array
    {
        return [
            self::MANAGE_LOTS,
            self::MANAGE_FLOORS,
            self::MANAGE_SLOTS,
            self::MANAGE_KIOSKS,
            // Granted for completeness rather than out of need: Gate::before lets the super
            // admin through every check regardless, but a role whose permission set is a
            // subset of the abilities its holder has is a lie waiting to be read.
            self::OPERATE_KIOSKS,
            self::VIEW_VEHICLES,
            self::CREATE_VEHICLES,
            self::UPDATE_VEHICLES,
            self::DELETE_VEHICLES,
            self::MANAGE_USERS,
        ];
    }

    /**
     * Every permission any role holds. The set of permissions created by sync().
     *
     * A union rather than the super-admin list alone, because a permission granted only to
     * a lesser role - `kiosks.operate` is exactly that - would otherwise never be created,
     * and syncPermissions() would fail on a name that has no row behind it.
     *
     * @return array<int, string>
     */
    public static function allPermissions(): array
    {
        return array_values(array_unique(array_merge(
            self::operatorPermissions(),
            self::lotAdminPermissions(),
            self::superAdminPermissions(),
        )));
    }

    /**
     * Create every role and permission if missing, then attach each role's permission set.
     *
     * Note what this deliberately does not do: delete permissions that are no longer in
     * the catalogue. A mass delete would bypass model events and leave orphaned
     * `role_has_permissions` / `model_has_permissions` rows behind. Retiring a permission
     * is a schema change, not something a seeder should decide.
     */
    public static function sync(): void
    {
        // Both directions matter, and the reason is not obvious.
        //
        // Spatie resolves a permission name through its registrar cache, not a live query:
        // Permission::findByName() asks the cache and throws PermissionDoesNotExist when the
        // name is absent from it. The cache is normally invalidated by model events.
        //
        // `db:seed` unsets the model event dispatcher for the whole run, so nothing
        // invalidates it here. Clearing before the writes stops a stale cache from making
        // findOrCreate() re-insert a permission that already exists; clearing *after* stops
        // the just-created permissions from being invisible to the lookups that follow.
        // Without the second call the very next syncPermissions() reports permissions that
        // are sitting in the database, which is what this method is supposed to guarantee.
        self::flushPermissionCache();

        $all = self::allPermissions();

        foreach ($all as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Re-seed the registrar so the names below resolve against what was just written.
        self::flushPermissionCache();

        Role::findOrCreate(User::ROLE_OPERATOR, 'web')
            ->syncPermissions(self::operatorPermissions());

        Role::findOrCreate(User::ROLE_LOT_ADMIN, 'web')
            ->syncPermissions(self::lotAdminPermissions());

        Role::findOrCreate(User::ROLE_SUPER_ADMIN, 'web')->syncPermissions($all);

        // Leave the cache empty rather than half-populated: the next lookup rebuilds it from
        // the database, which is the only state guaranteed to match it.
        self::flushPermissionCache();
    }

    /**
     * Drop Spatie's cached permission set, so the next lookup re-reads it from the database.
     */
    public static function flushPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
