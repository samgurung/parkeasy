<div class="relative min-h-[calc(100dvh_-_4rem)] w-full overflow-hidden bg-[#070312] text-white flex flex-col">

    {{-- Background gradient & orbs --}}
    <div class="kiosk-bg absolute inset-0"></div>
    <div class="kiosk-orb pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full bg-teal-400 opacity-30 blur-[100px]"></div>
    <div class="kiosk-orb pointer-events-none absolute top-1/3 -right-32 h-[28rem] w-[28rem] rounded-full bg-fuchsia-500 opacity-25 blur-[120px]"></div>
    <div class="kiosk-orb pointer-events-none absolute -bottom-32 left-1/4 h-96 w-96 rounded-full bg-amber-500 opacity-20 blur-[110px]"></div>
    <div class="pointer-events-none absolute inset-0 kiosk-grain opacity-30"></div>

    <div class="relative z-10 flex w-full max-w-5xl flex-1 flex-col mx-auto px-6 py-6">

        {{-- Page header --}}
        <header class="flex items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-black uppercase tracking-widest">
                    <span class="text-sky-400">Setup</span> — Staff
                </h1>
                <p class="mt-2 max-w-xl text-xs text-white/50">
                    An account is a role plus the lots it works in. A lot admin and an operator are
                    both limited to the lots ticked here; a super admin reaches every lot by role
                    and needs none.
                </p>
            </div>
            <p class="hidden max-w-xs text-right text-xs text-white/50 sm:block">
                <i class="fas fa-circle-info mr-1 text-sky-400"></i>
                Super admin only. Gate operators sign in here to reach their lot's kiosk.
            </p>
        </header>

        {{-- Add staff form --}}
        <section class="rounded-3xl border border-white/15 bg-white/10 px-6 py-6 shadow-2xl backdrop-blur-xl mb-8">
            <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.3em] text-white/50 flex items-center gap-2">
                <i class="fas fa-user-plus text-sky-400"></i> Add Staff Account
            </h2>
            <form wire:submit="add" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="name">Full name</label>
                    <input id="name" wire:model="name" type="text" placeholder="e.g. Bakiwe Marak"
                           class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                    @error('name') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="email">Email</label>
                    <input id="email" wire:model="email" type="email" placeholder="name@parkeasy.test"
                           class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                    @error('email') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="password">Password</label>
                    <input id="password" wire:model="password" type="password" autocomplete="new-password" placeholder="At least 8 characters"
                           class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                    @error('password') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="role">Role</label>
                    <select id="role" wire:model.live="role"
                            class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white outline-none focus:border-sky-400 focus:bg-white/15">
                        @foreach (\App\Livewire\Admin\UserManager::ROLES as $value => $label)
                            <option value="{{ $value }}" class="text-white bg-slate-800">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>

                {{-- Lot assignment. Hidden for a super admin, whose reach is the role itself,
                     so the picker would only collect rows nothing reads. --}}
                @if ($role !== \App\Models\User::ROLE_SUPER_ADMIN)
                    <fieldset class="sm:col-span-2">
                        <legend class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">
                            Lots this account works in
                        </legend>
                        @if ($lots->isEmpty())
                            <p class="mt-2 rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-xs text-amber-200">
                                <i class="fas fa-triangle-exclamation mr-1"></i>
                                No lots available. An account with no lot reaches nothing — add a lot first.
                            </p>
                        @else
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($lots as $lot)
                                    <label wire:key="add-lot-{{ $lot->id }}"
                                           class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-white/15 bg-white/5 px-4 py-3 text-sm transition hover:bg-white/10">
                                        <input type="checkbox" wire:model="lotIds" value="{{ $lot->id }}"
                                               class="h-4 w-4 rounded border-white/30 bg-white/10 text-sky-500 focus:ring-sky-400" />
                                        <span class="min-w-0">
                                            <span class="block truncate font-bold">#{{ $lot->lot_number }} — {{ $lot->name }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('lotIds') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                        @error('lotIds.*') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </fieldset>
                @endif

                <div class="sm:col-span-2 flex items-center gap-4">
                    <button type="submit"
                            class="rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 px-6 py-3 text-sm font-black uppercase tracking-[0.2em] text-white shadow-lg hover:brightness-110 transition">
                        <i class="fas fa-user-plus mr-2"></i> Add Account
                    </button>
                    <p class="text-xs text-white/40">
                        The account signs in at <a href="{{ route('login') }}" class="text-sky-300 underline">/login</a>;
                        an operator lands straight on their gate.
                    </p>
                </div>
            </form>
        </section>

        {{-- Staff list --}}
        <section>
            <h2 class="mb-3 text-xs font-bold uppercase tracking-[0.3em] text-white/50 flex items-center gap-2">
                <i class="fas fa-users text-teal-400"></i> Staff Accounts
            </h2>

            @forelse ($users as $account)
                <div wire:key="user-{{ $account->id }}"
                     class="mb-4 rounded-2xl border border-white/15 bg-white/10 px-6 py-5 shadow-xl backdrop-blur-xl">

                    @if ($editingId === $account->id)
                        {{-- Inline edit form --}}
                        <form wire:submit="save" class="grid grid-cols-1 gap-4 sm:grid-cols-2 items-start">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Full name</label>
                                <input wire:model="editName" type="text"
                                       class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-white outline-none focus:border-sky-400 focus:bg-white/15" />
                                @error('editName') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Email</label>
                                <input wire:model="editEmail" type="email"
                                       class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-white outline-none focus:border-sky-400 focus:bg-white/15" />
                                @error('editEmail') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">
                                    New password <span class="normal-case tracking-normal text-white/35">(blank keeps it)</span>
                                </label>
                                <input wire:model="editPassword" type="password" autocomplete="new-password" placeholder="Unchanged"
                                       class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                                @error('editPassword') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Role</label>
                                <select wire:model.live="editRole"
                                        @disabled($account->is(auth()->user()))
                                        class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-white outline-none focus:border-sky-400 focus:bg-white/15 disabled:cursor-not-allowed disabled:opacity-50">
                                    @foreach (\App\Livewire\Admin\UserManager::ROLES as $value => $label)
                                        <option value="{{ $value }}" class="text-white bg-slate-800">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('editRole') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                                @if ($account->is(auth()->user()))
                                    <p class="mt-1 text-[11px] text-amber-300">
                                        <i class="fas fa-lock mr-1"></i> Your own role cannot be changed here.
                                    </p>
                                @endif
                            </div>

                            @if ($editRole !== \App\Models\User::ROLE_SUPER_ADMIN)
                                <fieldset class="sm:col-span-2">
                                    <legend class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">
                                        Lots this account works in
                                    </legend>
                                    @if ($lots->isEmpty())
                                        <p class="mt-2 rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-xs text-amber-200">
                                            No lots available, so this account will reach nothing.
                                        </p>
                                    @else
                                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                            @foreach ($lots as $lot)
                                                <label wire:key="edit-lot-{{ $account->id }}-{{ $lot->id }}"
                                                       class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-white/15 bg-white/5 px-4 py-3 text-sm transition hover:bg-white/10">
                                                    <input type="checkbox" wire:model="editLotIds" value="{{ $lot->id }}"
                                                           class="h-4 w-4 rounded border-white/30 bg-white/10 text-sky-500 focus:ring-sky-400" />
                                                    <span class="block truncate font-bold">#{{ $lot->lot_number }} — {{ $lot->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif
                                    @error('editLotIds') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                                    @error('editLotIds.*') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                                </fieldset>
                            @endif

                            <div class="sm:col-span-2 flex gap-3">
                                <button type="submit"
                                        class="rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 px-5 py-2 text-sm font-black uppercase tracking-[0.2em] text-white shadow hover:brightness-110 transition">
                                    <i class="fas fa-check mr-1"></i> Save
                                </button>
                                <button type="button" wire:click="cancel"
                                        class="rounded-xl border border-white/20 bg-white/10 px-5 py-2 text-sm font-bold text-white/70 hover:bg-white/20 transition">
                                    Cancel
                                </button>
                            </div>
                        </form>

                    @else
                        {{-- Read view --}}
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-4">
                                <span @class([
                                    'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-xl font-black text-white shadow',
                                    'bg-gradient-to-br from-amber-400 to-orange-600' => $account->isSuperAdmin(),
                                    'bg-gradient-to-br from-sky-400 to-blue-600' => ! $account->isSuperAdmin(),
                                ])>
                                    <i class="fas fa-user-shield"></i>
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-lg font-bold">{{ $account->name }}</span>
                                        @if ($account->is(auth()->user()))
                                            <span class="rounded-lg border border-sky-400/30 bg-sky-500/10 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-sky-300">
                                                You
                                            </span>
                                        @endif
                                        @if ($account->getRoleNames()->isEmpty())
                                            <span class="rounded-lg border border-rose-400/30 bg-rose-500/10 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-rose-300">
                                                No role — reaches nothing
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-white/50">
                                        <span class="font-mono">{{ $account->email }}</span>
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        <span @class([
                                            'rounded-lg border px-2 py-0.5 text-[10px] font-black uppercase tracking-widest',
                                            'border-amber-400/40 bg-amber-500/10 text-amber-300' => $account->isSuperAdmin(),
                                            'border-sky-400/40 bg-sky-500/10 text-sky-300' => $account->isLotAdmin(),
                                            'border-teal-400/40 bg-teal-500/10 text-teal-300' => ! $account->isSuperAdmin() && ! $account->isLotAdmin(),
                                        ])>
                                            {{ $account->isSuperAdmin() ? 'Super admin' : ($account->isLotAdmin() ? 'Lot admin' : 'Operator') }}
                                        </span>
                                        @if ($account->isSuperAdmin())
                                            <span class="text-[11px] text-white/40">every lot, by role</span>
                                        @else
                                            @forelse ($account->lots as $lot)
                                                <span wire:key="chip-{{ $account->id }}-{{ $lot->id }}"
                                                      class="rounded-lg border border-white/15 bg-white/5 px-2 py-0.5 text-[11px] text-white/60">
                                                    #{{ $lot->lot_number }} — {{ $lot->name }}
                                                </span>
                                            @empty
                                                <span class="text-[11px] text-amber-300">No lots assigned — reaches nothing</span>
                                            @endforelse
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-3">
                                <button wire:click="edit({{ $account->id }})"
                                        class="rounded-xl border border-sky-400/40 bg-sky-500/10 px-4 py-2 text-xs font-bold uppercase tracking-widest text-sky-300 hover:bg-sky-500/20 transition">
                                    <i class="fas fa-pen mr-1"></i> Edit
                                </button>
                                @if (! $account->is(auth()->user()))
                                    <button wire:click="confirmDelete({{ $account->id }})"
                                            class="rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-xs font-bold uppercase tracking-widest text-rose-300 hover:bg-rose-500/20 transition">
                                        <i class="fas fa-trash mr-1"></i> Delete
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-white/10 bg-white/5 px-6 py-10 text-center text-white/40 italic">
                    No staff accounts yet. Add one above.
                </div>
            @endforelse
        </section>

    </div>

    {{-- Delete confirmation modal --}}
    @if ($deletingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md">
            <div class="result-pop relative w-full max-w-sm rounded-[2rem] border border-rose-400/30 bg-white/15 p-10 text-center shadow-2xl backdrop-blur-2xl">
                <i class="fas fa-triangle-exclamation text-6xl text-rose-400 mb-4"></i>
                <div class="text-2xl font-black uppercase tracking-widest mb-2">Delete Account?</div>
                <p class="text-sm text-white/60 mb-6">
                    The person loses access immediately. Their lot assignments are removed with them.
                </p>
                <div class="flex gap-4 justify-center">
                    <button wire:click="delete"
                            class="rounded-xl bg-gradient-to-br from-rose-500 to-orange-500 px-6 py-3 text-sm font-black uppercase tracking-widest text-white shadow hover:brightness-110 transition">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                    <button wire:click="cancelDelete"
                            class="rounded-xl border border-white/20 bg-white/10 px-6 py-3 text-sm font-bold text-white/70 hover:bg-white/20 transition">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif

    <style>
        .kiosk-bg {
            background: linear-gradient(135deg, #14104a 0%, #341a7a 22%, #7a1f6b 46%, #bd3a3a 70%, #f26b1d 100%);
            background-size: 350% 350%;
            animation: kioskShift 16s ease-in-out infinite;
        }
        .kiosk-grain {
            background-image: radial-gradient(rgba(255,255,255,0.12) 1px, transparent 1px);
            background-size: 5px 5px;
        }
        .kiosk-orb { animation: kioskFloat 12s ease-in-out infinite; }
        .kiosk-orb:nth-of-type(2) { animation-delay: -4s; }
        .kiosk-orb:nth-of-type(3) { animation-delay: -8s; }
        @keyframes kioskShift {
            0%   { background-position: 0% 0%; }
            50%  { background-position: 100% 100%; }
            100% { background-position: 0% 0%; }
        }
        @keyframes kioskFloat {
            0%, 100% { transform: translateY(0) scale(1); }
            50%      { transform: translateY(-26px) scale(1.06); }
        }
        @keyframes kioskPop {
            0%   { transform: scale(0.78); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .result-pop { animation: kioskPop 0.35s cubic-bezier(0.2, 0.9, 0.3, 1.2) both; }
    </style>

</div>