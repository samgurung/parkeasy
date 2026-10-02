<?php

namespace Database\Seeders;

use App\Models\Access;
use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the access catalogue, the single super admin, and one lot admin per seeded lot.
 *
 * Idempotent: roles and permissions are synced to the catalogue, and accounts are matched by
 * email. A re-run does not reset an existing account's password, so a password that has since
 * been changed is neither silently reverted nor misreported.
 */
class AdminUserSeeder extends Seeder
{
    private bool $superPasswordGenerated = false;

    private bool $lotPasswordGenerated = false;

    /** The password handed to the account being seeded, so it can be reported accurately. */
    private string $lastPassword = '';

    public function run(): void
    {
        Access::sync();

        $superEmail = (string) (config('services.super_admin_email') ?: 'superadmin@parkeasy.test');
        $superExisted = User::where('email', $superEmail)->exists();

        // The super admin is attached to no lots: their reach is the whole portfolio, which
        // is expressed by isSuperAdmin() rather than by attaching every lot by hand. syncRoles
        // rather than assignRole, so re-seeding cannot leave a stray extra role behind.
        $super = $this->account($superEmail, 'Super Admin', $this->superAdminPassword());
        $super->syncRoles(User::ROLE_SUPER_ADMIN);
        $super->lots()->sync([]);

        $this->command?->info('Super admin ready: '.$super->email);

        $this->reportPassword('super admin', $superExisted, $this->lastPassword, $this->superPasswordGenerated);

        $managed = $this->seedLotAdmins($super);

        $this->reportOrphanedLotAdmins($managed);
    }

    /**
     * One lot admin per lot, keyed on the lot number.
     *
     * @param  User  $super  the one account that must never be reshaped by the lot loop
     * @return array<int, string> the emails this run owns
     */
    private function seedLotAdmins(User $super): array
    {
        $domain = $this->emailDomain();
        $managed = [];

        foreach (ParkingLot::orderBy('lot_number')->get() as $lot) {
            // Keyed on lot_number, never on the lot's name. Names are editable, and distinct
            // names can slugify to the same string - "Tura Bus Stand" and "Tura-Bus-Stand" both
            // reduce to tura.bus.stand, which would silently hand two lots to one account.
            // lot_number is unique and never changes, so this cannot collide or go stale.
            $email = sprintf('lot-%d-admin@%s', $lot->lot_number, $domain);

            // Belt and braces: a lot called "Superadmin" must not be able to reach the one
            // account whose reach is every lot, whatever naming scheme is in use.
            if ($email === $super->email) {
                $this->command?->warn("  skipped lot '{$lot->name}': its admin address collides with the super admin");

                continue;
            }

            $existed = User::where('email', $email)->exists();
            $password = $this->lotAdminPassword();
            $generated = $this->lotPasswordGenerated;

            $admin = $this->account($email, $lot->name.' Admin', $password);

            // Scoped to the break-glass account itself rather than to the role: a lot admin
            // who was wrongly granted super_admin is corrected by the syncRoles below, but
            // the one account that administers every lot is never demoted by a re-seed.
            if ($admin->is($super)) {
                $this->command?->warn("  skipped '{$email}': that address is the super admin's own");

                continue;
            }

            $admin->syncRoles(User::ROLE_LOT_ADMIN);
            $admin->lots()->syncWithoutDetaching([$lot->id]);

            $this->command?->info(sprintf('  lot admin: %s → %s', $email, $lot->name));
            $this->reportPassword("  '{$lot->name}' admin", $existed, $password, $generated);

            $managed[] = $email;
        }

        return $managed;
    }

    /**
     * Accounts that used to be lot admins but no longer match any lot. Reported rather than
     * deleted: an account may be in use by a person, and silently destroying credentials is
     * worse than leaving an operator to decide.
     *
     * @param  array<int, string>  $managed
     */
    private function reportOrphanedLotAdmins(array $managed): void
    {
        $orphans = User::role(User::ROLE_LOT_ADMIN)
            ->whereNotIn('email', $managed)
            ->get();

        if ($orphans->isEmpty()) {
            return;
        }

        $this->command?->warn(sprintf(
            '%d lot-admin account(s) no longer match any lot and were left untouched:',
            $orphans->count()
        ));

        foreach ($orphans as $orphan) {
            $this->command?->warn('  - '.$orphan->email);
        }
    }

    /**
     * A published default password is a permanent backdoor, so a configured one is only ever
     * used when an operator has deliberately set it - and a weak one is called out loudly.
     */
    private function lotAdminPassword(): string
    {
        $this->lotPasswordGenerated = false;

        if ($configured = config('services.lot_admin_password')) {
            $password = (string) $configured;

            if (Str::length($password) < 12) {
                $this->command?->warn('  WARNING: PARKEASY_LOT_ADMIN_PASSWORD is under 12 characters');
            }

            return $this->lastPassword = $password;
        }

        $this->lotPasswordGenerated = true;

        return $this->lastPassword = Str::password(20);
    }

    /**
     * The super admin's password is generated unless one is deliberately configured: this is
     * the account that can reach every lot, so it must not be reachable with a value that is
     * published in this file.
     */
    private function superAdminPassword(): string
    {
        if ($configured = config('services.super_admin_password')) {
            return $this->lastPassword = (string) $configured;
        }

        $this->superPasswordGenerated = true;

        return $this->lastPassword = Str::password(20);
    }

    /**
     * Only ever report a password that was actually applied. Printing a freshly generated one
     * on a re-run would show a value that does not open the account, and printing a configured
     * default for an existing account would invite someone to try a password that no longer works.
     */
    private function reportPassword(string $label, bool $existed, string $password, bool $generated): void
    {
        if ($existed) {
            $this->command?->info("  {$label} password unchanged (account already existed)");

            return;
        }

        $this->command?->info('  '.$label.' password'.($generated ? ' (generated, shown once)' : '').': '.$password);
    }

    private function emailDomain(): string
    {
        // Taken from the super admin's address so one env var keeps the whole domain
        // consistent, falling back to the local test domain.
        $superEmail = (string) (config('services.super_admin_email') ?: 'superadmin@parkeasy.test');

        return Str::contains($superEmail, '@') ? Str::after($superEmail, '@') : 'parkeasy.test';
    }

    private function account(string $email, string $name, string $password): User
    {
        return User::where('email', $email)->first() ?? User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
    }
}
