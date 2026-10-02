<?php

namespace Database\Seeders;

use App\Models\Access;
use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the access catalogue, the single super admin, and one lot admin plus one operator
 * per seeded lot.
 *
 * Idempotent: roles and permissions are synced to the catalogue, and accounts are matched by
 * email. A re-run does not reset an existing account's password, so a password that has since
 * been changed is neither silently reverted nor misreported.
 *
 * The lot admin and the operator are seeded from one loop over the lots, because they differ
 * only in their role, their address fragment and their display name - and a second hand-written
 * loop over the same lots is exactly where the two would drift apart.
 */
class AdminUserSeeder extends Seeder
{
    /** Whether the password resolved last was a default rather than a configured one. */
    private bool $passwordDefaulted = false;

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
        $super = $this->account(
            $superEmail,
            'Super Admin',
            $this->resolvePassword(config('services.super_admin_password'), 'superadmin123')
        );
        $super->syncRoles(User::ROLE_SUPER_ADMIN);
        $super->lots()->sync([]);

        $this->command?->info('Super admin ready: '.$super->email);

        $this->reportPassword('super admin', $superExisted, $this->lastPassword);

        // Lot admins own the setup of their lot; operators run the gates. Both are lot-scoped
        // through the same pivot, so both are seeded per lot and neither is site-wide.
        $managedAdmins = $this->seedLotScopedAccounts(
            $super,
            User::ROLE_LOT_ADMIN,
            'admin',
            'Admin',
            'lot_admin_password'
        );

        $managedOperators = $this->seedLotScopedAccounts(
            $super,
            User::ROLE_OPERATOR,
            'operator',
            'Operator',
            'operator_password'
        );

        $this->reportOrphanedAccounts(User::ROLE_LOT_ADMIN, 'lot-admin', $managedAdmins);
        $this->reportOrphanedAccounts(User::ROLE_OPERATOR, 'operator', $managedOperators);
    }

    /**
     * One account per lot for a lot-scoped role, keyed on the lot number.
     *
     * Shared by the lot admin and the operator so that the two roles cannot come to disagree
     * about which account belongs to which lot - a mismatch there would silently hand one
     * person's gates to another.
     *
     * @param  string  $role  the Spatie role name to assign
     * @param  string  $emailSegment  the address fragment, e.g. 'operator' -> lot-3-operator@…
     * @param  string  $nameSuffix  the display-name suffix, e.g. 'Operator' -> "Tura Operator"
     * @param  string  $passwordConfigKey  the services config key holding a shared password
     * @return array<int, string> the emails this run owns
     */
    private function seedLotScopedAccounts(
        User $super,
        string $role,
        string $emailSegment,
        string $nameSuffix,
        string $passwordConfigKey
    ): array {
        $domain = $this->emailDomain();
        $managed = [];

        foreach (ParkingLot::orderBy('lot_number')->get() as $lot) {
            // Keyed on lot_number, never on the lot's name. Names are editable, and distinct
            // names can slugify to the same string - "Tura Bus Stand" and "Tura-Bus-Stand" both
            // reduce to tura.bus.stand, which would silently hand two lots to one account.
            // lot_number is unique and never changes, so this cannot collide or go stale.
            $email = sprintf('lot-%d-%s@%s', $lot->lot_number, $emailSegment, $domain);

            // Belt and braces: a lot called "Superadmin" must not be able to reach the one
            // account whose reach is every lot, whatever naming scheme is in use.
            if ($email === $super->email) {
                $this->command?->warn("  skipped lot '{$lot->name}': its {$emailSegment} address collides with the super admin");

                continue;
            }

            $existed = User::where('email', $email)->exists();
            $password = $this->resolvePassword(config('services.'.$passwordConfigKey), $email);

            $user = $this->account($email, $lot->name.' '.$nameSuffix, $password);

            // Scoped to the break-glass account itself rather than to the role: an account
            // that was wrongly granted super_admin is corrected by the syncRoles below, but
            // the one account that administers every lot is never demoted by a re-seed.
            if ($user->is($super)) {
                $this->command?->warn("  skipped '{$email}': that address is the super admin's own");

                continue;
            }

            // syncRoles, not assignRole: re-seeding is authoritative about what this address
            // is for, so a stale extra role cannot survive a second run.
            $user->syncRoles($role);

            // syncWithoutDetaching, not sync: a seeded lot account keeps whatever other lots a
            // human has since attached it to by hand. The seeder owns this lot, not the rest.
            $user->lots()->syncWithoutDetaching([$lot->id]);

            $this->command?->info(sprintf('  %s: %s → %s', $role, $email, $lot->name));
            $this->reportPassword("  '{$lot->name}' {$emailSegment}", $existed, $password);

            $managed[] = $email;
        }

        return $managed;
    }

    /**
     * Accounts holding a lot-scoped role that no longer match any lot. Reported rather than
     * deleted: an account may be in use by a person, and silently destroying credentials is
     * worse than leaving an operator to decide.
     *
     * @param  array<int, string>  $managed
     */
    private function reportOrphanedAccounts(string $role, string $label, array $managed): void
    {
        $orphans = User::role($role)
            ->whereNotIn('email', $managed)
            ->get();

        if ($orphans->isEmpty()) {
            return;
        }

        $this->command?->warn(sprintf(
            '%d %s account(s) no longer match any lot and were left untouched:',
            $orphans->count(),
            $label
        ));

        foreach ($orphans as $orphan) {
            $this->command?->warn('  - '.$orphan->email);
        }
    }

    /**
     * The password handed to a freshly created account, remembered for the report.
     *
     * A lot-scoped account - a lot admin or an operator - defaults to its own address, so the
     * credentials someone needs are the ones printed at the bottom of a fresh seed: no env var
     * to look up and nothing to copy out of a log. It also means the password is unique per
     * account, so one account being guessed at says nothing about the others.
     *
     * The super admin's fallback is instead a fixed, published value, because it is the
     * account a new install is locked out of otherwise and its credentials are printed on a
     * fresh seed. In both cases a configured password still wins, for whoever wants one shared
     * value.
     */
    private function resolvePassword(mixed $configured, string $fallback): string
    {
        $this->passwordDefaulted = false;

        if ($configured) {
            return $this->lastPassword = (string) $configured;
        }

        $this->passwordDefaulted = true;

        return $this->lastPassword = $fallback;
    }

    /**
     * Only ever report a password that was actually applied. Both defaults are now predictable,
     * so printing one for an account that already exists would invite a failed sign-in - the
     * account is only reachable by whatever password it ended up with.
     */
    private function reportPassword(string $label, bool $existed, string $password): void
    {
        if ($existed) {
            $this->command?->info("  {$label} password unchanged (account already existed)");

            return;
        }

        $this->command?->info('  '.$label.' password'.($this->passwordDefaulted ? ' (default)' : '').': '.$password);
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
