{{-- The way in. Behind this sits everything that moves a vehicle: the kiosk terminal at /
     is the entry and exit screen, so reaching it means being somebody. A gate tablet signs
     in once and then stays bound to its own gate. --}}

<div class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-md items-center px-4 py-12">
    <div class="w-full rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/40">
        <div class="mb-6 text-center">
            <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-lg text-white shadow-lg shadow-blue-500/30">
                <i class="fas fa-user-shield"></i>
            </span>
            <h1 class="text-xl font-black uppercase tracking-widest text-white">Sign in</h1>
            <p class="mt-1 text-sm text-white/50">Gate operators, lot admins and super admins.</p>
        </div>

        <form wire:submit="login" class="space-y-4">
            <div>
                <label for="email" class="mb-1.5 block text-xs font-bold uppercase tracking-widest text-white/50">Email</label>
                <input id="email" type="email" wire:model="email" autocomplete="username" autofocus
                       class="w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                @error('email')
                    <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-xs font-bold uppercase tracking-widest text-white/50">Password</label>
                <input id="password" type="password" wire:model="password" autocomplete="current-password"
                       class="w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                @error('password')
                    <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="login"
                    class="w-full rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 px-4 py-3 font-bold uppercase tracking-widest text-white shadow-lg shadow-blue-500/25 transition hover:brightness-110 disabled:opacity-60">
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading wire:target="login">Signing in…</span>
            </button>
        </form>

        <p class="mt-6 flex items-start gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-xs text-white/45">
            <i class="fas fa-circle-info mt-0.5 shrink-0 text-sky-300"></i>
            <span>An operator is sent straight to their lot's gate and stays there — one sign-in
                per shift, and the tablet remembers its own gate across a restart. Lot admins and
                super admins land in the admin panel.</span>
        </p>
    </div>
</div>
