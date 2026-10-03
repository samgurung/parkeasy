# ParkEasy — Application Architecture & Implementation Guide

ParkEasy is a **smart parking management platform** built on Laravel. It combines two
physical systems in one codebase:

1. **RFID-based entry/exit** — drivers tap a registered RFID card at a kiosk terminal
   (a browser opened on a tablet), and the app records the entry, computes the parking
   fee on exit, and shows live results on the kiosk screen.
2. **IR-sensor-based slot occupancy** — per-parking-slot infrared sensor nodes (ESP32)
   POST to the backend whenever a car enters or leaves a bay. The app toggles the slot's
   state and pushes the change to monitoring dashboards in real time.

This document explains the URL endpoints, the overall data flow, the tech stack, the
database structure, the models, the controllers, and the Livewire components.

---

## 1. High-level system picture

```
┌────────────────────┐        ┌───────────────────────────────┐        ┌──────────────────┐
│  RFID reader/hard- │ POST   │  Laravel app (backend)        │ WS ───▶│  Reverb broker   │
│  ware or manual    │ ───────▶  POST /api/rfid-scan*         │        │ (broadcast,      │
│  keyboard/scan     │        │  POST /api/rfid-scan/enrol    │        │  pusher protocol)│
└────────────────────┘        │  POST /api/slot-status ◀──    │        └─────────┬────────┘
┌────────────────────┐        │  Livewire pages (kiosk, admin)│                  │
│ ESP32 IR sensor    │ POST   │  Eloquent models + DB         │          ┌───────┴─────┐
│ nodes (per slot)   │ ───────▶                               │          │  Browsers   │
│                    │        │                               │          │ - kiosks    │
└────────────────────┘        └───────────────────────────────┘          │ - admins    │
                                                                         └─────────────┘
```

### Actors

| Actor | Description |
|---|---|
| **Kiosk terminal** | A browser page (`/` + `?kiosk=<key>`) on a tablet behind a gate, **behind a login** — it is the entry and exit screen, so reaching it means being somebody. Each kiosk is registered as an ENTRY or EXIT gate, so the card only has to be scanned — a hardware RFID reader on the keyboard-USB bus or manual typing, with no direction to pick. Normally run by an **operator** (below) signing in once per shift; a lot admin or super admin may also open it to preview a gate, and picks that gate with a **kiosk dropdown** on the terminal itself — shown only when nothing is bound, so "wrong gate" is Unbind followed by one tap rather than a trip to `/admin/kiosks`. |
| **ESP32 IR sensor node** | A low-cost board with one or more IR beam sensors per slot. It fires an HTTP POST to `/api/slot-status` each time a beam is broken/restored. |
| **Super admin** | Exactly one account, attached to no lots and therefore reaching every lot. Owns site-wide configuration (creating lots), the corrections that touch other people's records (editing/deleting vehicle registrations), and staff accounts. |
| **Lot admin** | Attached to one or more lots via `user_parking_lot`. Manages the operational shape of their own lots — floors, slot counts, kiosks, and the card registry — and can bind a card to a vehicle at the gate. Sign-in lands them in the `/admin/*` panel. |
| **Operator** | Gate staff, attached to one or more lots. Holds a single permission (`kiosks.operate`) and can do exactly one thing: run the terminal for a kiosk in their own lot. Refused every `/admin/*` page and redirected to their gate instead; refused any kiosk outside their lot. |
| **Driver** | Carries an RFID card. Can self-register a new card at the kiosk on first entry. |
| **Viewer** | Opens `/lots` or `/slots` to see live occupancy. Still public — a wall monitor has no account and no keyboard, and the figures it shows are not sensitive. |

**Why the terminal needs a login at all.** `/` is the entry and exit screen: from it a card is
read, a vehicle is charged and a vehicle is released. Leaving it public would mean anyone who
opened the tablet at a barrier could do that as an operator, so it needs a login like every
other page that changes state. The point of a login here is not *authorisation* — an operator
holds a single permission — but *attribution*: the gate is tied to a named person rather than
to whoever happens to be holding the tablet.

That is a deliberate trade. The cheapest deployment, an account-less tablet that anyone can
walk up to, is gone. In exchange the binding survives a restart for the tablet that matters:
the operator signs in once per shift and the gate stays theirs across a power cut, because
the binding is per-gate hardware rather than per-person. See §5A.

The login is the boundary, not a role. Any account that can authenticate reaches the terminal,
because the question "may this person run a gate?" is asked by the kiosk picker and the scan
API against the specific kiosk, not by the route. An account with no role signs in and lands
on the unbound page — visible as broken rather than silently treated as an operator.

**Why a landing page rather than a redirect.** The login is enforced in `Home::render()`
instead of `->middleware('auth')`, so a guest gets `livewire.landing` — one line on what the
app is, and a Sign in button — rather than being dropped on a login form. A redirect
says "you may not see this" and nothing else; for someone who arrived from a link or a QR
code, that is a dead end with no explanation. Two further reasons fell out of it:

- The kiosk URL stays `/?kiosk=<key>` with no path prefix. That URL is what gets printed on
  labels and generated by the admin kiosk list, so moving the terminal behind a prefix would
  have invalidated every label already stuck to a barrier.
- Nothing stores an intended URL for the hop, so the post-login destination comes from
  `User::landingUrl()` by role. For an operator that is the better answer anyway — see §5A.

---

## 2. Technology stack and how it fits

| Tech | Role in this application |
|---|---|
| **Laravel 13 (PHP)** | Application framework. Routes, controllers, Eloquent, validation, events, broadcasting, cache. |
| **Livewire 4** | Renders every interactive page as an SFC/class-based component with zero custom JS for admin/CRUD and reactive dashboards (`wire:poll`, `wire:model`). |
| **Laravel Reverb** | The WebSocket server that both broadcast events stream through. The browser connects via **laravel-echo + pusher-js** (Reverb speaks the Pusher protocol). `BROADCAST_CONNECTION=reverb`. |
| **Laravel Echo + Pusher JS** | Client-side subscription to the public channels `kiosk.{key}` and `parking-slots`; live-updates the kiosk result overlay and the slot monitor tiles without polling. |
| **Tailwind CSS 4 + Vite** | Styling and asset bundling (`resources/js/app.js` → Echo bootstrap, Font Awesome icons). |
| **Redis** | Session driver and queue driver (`predis`). |
| **Database cache** | `CACHE_STORE=database`; general-purpose cache. |
| **MariaDB / SQLite** | Relational store. Dev environment uses MariaDB; tests use SQLite via `phpunit.xml`. Migrations are portable across both. |
| **`ShouldBroadcastNow` events** | Both `RfidScanned` and `SlotStatusChanged` set `broadcastQueue = 'null'` so broadcasts are pushed synchronously instead of being queued — dashboards update instantly, no queue worker required for live updates. |
| **Tests** | Pest/PHPUnit feature tests (`tests/Feature`) exercise the API endpoints, page rendering and Livewire component behavior. |

**Why these fit together:** the product needs *low-cost hardware*, *screen-driven kiosks*,
and *instant visual feedback*. Laravel provides the HTTP/state machine; Reverb provides
the live push so a Euro "slot flips green/red the second a car leaves"; Livewire keeps
the admin/UI work plain server-side PHP; and the stateless JSON APIs are simple enough
for an ESP32 to consume.

---

## 3. URL endpoints

### 3.1 Web routes (`routes/web.php`) — Livewire pages

All web routes render a Livewire component in the shared `components.layouts.app` layout.

| Method | Path | Name | Livewire component | Purpose |
|---|---|---|---|---|
| GET | `/` | `home` | `App\Livewire\Home` | **Two pages behind one URL**, branched in `Home::render()`: a guest gets the **landing page** (`livewire.landing` — one line on what the app is, and a Sign in button); once signed in it is the **kiosk terminal**. `?kiosk=<key>` binds it to a registered kiosk (an operator's binding is remembered across browser restarts); shows the ENTRY/EXIT gate indicator, manual card input, and a recent-scan feed. An operator with no kiosk resolved gets a **picker** of the gates in their own lot instead. Staff who can manage kiosks get a **kiosk selector** above the gate panes to switch or pick one. |
| GET | `/kiosk/forget` | `ForgetKioskController` | Releases the browser from its bound kiosk (clears the cookie + session) and returns to the **terminal**, where whatever way this account picks a kiosk is offered again — the operator's card picker or a staff member's dropdown. Role-independent: unbinding is not "go and manage kiosks", it is "stop showing me this gate". |
| GET | `/lots` | `lots.overview` | `App\Livewire\LotOverview` | Live **lot report** — all lots with free/occupied counts, per-type (2W/4W) occupancy, search, sort. Polls every 10s. |
| GET | `/slots` | `slots.dashboard` | `App\Livewire\SlotDashboard` | Live **slot monitor** — per-floor schematic of slot tiles; updates instantly over Echo. Lot selector + link to floor config. |
| GET | `/admin/floors` | `admin.floors` | `App\Livewire\Admin\FloorManager` | Create/edit/delete **floors & slots**, and designate per-slot 2W/4W type. Supports `?lot=<id>` to scope the list. |
| GET | `/admin/lots` | `admin.lots` | `App\Livewire\Admin\LotManager` | CRUD for **parking lots** (name, address, per-type hourly rates). |
| GET | `/admin/kiosks` | `admin.kiosks` | `App\Livewire\Admin\KioskManager` | Register/link/unlink **kiosks** to parking lots and choose each one's ENTRY/EXIT gate type; generates the `?kiosk=<key>` URL. |
| GET | `/admin/users` | `admin.users` | `App\Livewire\Admin\UserManager` | Create/edit/delete **staff accounts**: exactly one role (`super_admin` / `lot_admin` / `operator`) plus the lots a lot-scoped account works in. Super admin only (`users.manage`). |
| GET | `/up` | (health) | — | Laravel health endpoint (registered in `bootstrap/app.php`). |

> Note: only the `/admin/*` group carries an `auth` middleware. `/` needs a login too — it is
> the entry and exit screen (§5A) — but enforces it in the component rather than the route, so
> that a guest gets a landing page explaining the app instead of a bare redirect to the login
> form. `/lots`, `/slots` and `/kiosk/forget` are public: the first two only display
> occupancy, which is not sensitive, and the last is the escape hatch a shared machine needs
> *before* anyone has signed in. Per `PITCH.md` the remaining known gap is machine-to-machine
> auth: the `/api/*` routes below are open, including the scan endpoints the browser itself
> calls. The intended hardening is API tokens/sensor auth. See §10.

### 3.2 API routes (`routes/api.php`) — machine-to-machine JSON

| Method | Path | Controller@method | Purpose |
|---|---|---|---|
| POST | `/api/rfid-scan` | `RfidScannedController@rfidScanned` | A scan from a kiosk. Requires `kiosk`; the kiosk's registered **type** decides entry vs exit, so a bare reader can post without any direction. Entry kiosk + unknown card → `enrolment_required`; entry kiosk + known card → `parked`; exit kiosk → `exit` with the fee. |
| POST | `/api/rfid-scan/enrol` | `RfidScannedController@enrol` | Bind a card to its vehicle on first use and park it in one step. ENTRY kiosks only — an exit gate has no visit to attach the card to. |
| POST | `/api/slot-status` | `SlotStatusController@update` | **ESP32 IR node** toggles a slot's occupancy. Body: `{"lot": 1, "floor": 0, "slot": 3}`. |

All API routes return JSON. `bootstrap/app.php` ensures `api/*` and JSON-expecting
requests render validation failures as JSON.

### 3.3 Broadcasting channels (`routes/channels.php`)

| Channel | Visibility | Used by |
|---|---|---|
| `kiosk.{key}` | Public (`return true`) | Per-kiosk feed. `RfidScanned` events with a kiosk key broadcast here so only that kiosk's browser(s) react. Also used as a fallback `kiosk.{lot}` channel when no kiosk key is known. |
| `parking-slots` | Public | `SlotStatusChanged` events from IR sensor toggles; consumed by the slot monitor page. |
| `rfid` | Public | Global fallback channel for `RfidScanned` when neither kiosk key nor lot is known. |
| `App.Models.User.{id}` | Private (default scaffold) | Unused in the current app. |

---

## 4. Database structure

### 4.1 Schema (as produced by the migrations)

```
users (default Laravel scaffold)
  id, name, email, email_verified_at, password, remember_token, timestamps

entries (pooled-card model: each visit is self-contained, no vehicles table)
  id, rfid_id (nullable, card code used for this visit), parking_lot_id (FK, nullable),
  vehicle_type (nullable), driver_name, vehicle_number, mobile_number,
  entry_time, exit_time, status ('awaiting_details'|'parked'|'exited'),
  amount (decimal 8,2, nullable), deleted_at (soft deletes), timestamps

parking_lots
  id, name, lot_number (unique), address, rate_two_wheeler, rate_four_wheeler, timestamps

parking_floors
  id, name, parking_lot_id (FK, required), floor_number (int), slot_count, timestamps
  UNIQUE(parking_lot_id, floor_number)     -- floor numbers restart per lot (0,1,2,…)

parking_slots
  id, parking_floor_id (FK, cascade delete), slot_number (1-based), label (nullable),
  is_occupied (bool), vehicle_type (default four_wheeler), last_updated_at, timestamps
  UNIQUE(parking_floor_id, slot_number)

kiosks
  id, name, key (unique), parking_lot_id (FK, nullable), timestamps
```

### 4.2 Entity relationships

```
ParkingLot  1───*  ParkingFloor   1───*  ParkingSlot     (floors → slots, cascade)
ParkingLot  1───*  Kiosk                          (each kiosk bound to one lot, nullable)
ParkingLot  1───*  Entry                          (each visit is a self-contained row)
```

### 4.3 Design notes baked into the schema

- **Floors belong to a lot, and floor numbers restart per lot.** The ESP32 addresses a
  slot by `(lot, floor, slot)`; the composite unique key `(parking_lot_id, floor_number)`
  lets floor number `0` exist in every lot.
- **Slots are materialized rows, not derived.** `ParkingFloor::syncSlots()` automatically
  creates slot rows `1..slot_count` whenever a floor is created/edited, so occupancy state
  (`is_occupied`) has somewhere to live and `last_updated_at` records the last sensor hit.
- **`entries.amount` is the computed fee** at exit; the entry also snapshots
  `vehicle_number`, `driver_name`, `mobile_number`, and `vehicle_type` so a historic
  record is self-contained even if the card is re-linked.
- **`kiosks.parking_lot_id` is nullable** to support *delinking* a kiosk (when it's
  unlinked, scans are quietly refused rather than mis-credited).
- The `status` enum evolved over time: `inside/exited` → `awaiting_details/parked/exited`.
  On SQLite the enum isn't rewritten (unsupported); the app only writes `parked`/`exited`.

---

## 5. Models

### 5.1 `Vehicle` (`app/Models/Vehicle.php`)

The registered card holder. `rfid_id` is the lookup key used at both gates.
`vehicle_type` remembers the 2W/4W so the next visit can skip asking.

- Relationships: `entries()` (hasMany).

### 5.1 `Entry` (`app/Models/Entry.php`)

One parking visit ("ticket") under the **pooled-card model** — a card is a reusable
visit token, not a permanent owner identity. Each visit snapshots its own card code
(`rfid_id`) plus driver name, vehicle number, mobile number and vehicle type, so a
historic record is self-contained even though the card is re-issued to another driver.

- Lifecycle: `parked` → `exited` (fee written to `amount`).
- Relationships: `parkingLot()` (belongsTo).
- Casts: `entry_time`, `exit_time` → Carbon, `amount` → float (fee arithmetic).

### 5.3 `ParkingLot` (`app/Models/ParkingLot.php`)

A physical parking site. Carries the per-type hourly rates and the constants
`VEHICLE_TWO_WHEELER`/`VEHICLE_FOUR_WHEELER` shared app-wide. Exposes computed
occupancy attributes:
- `total_slots` (sum of floor `slot_count`s),
- `occupied_slots` (count of `is_occupied` slots),
- `free_slots`,
- `occupancy_pct`.

Helpers: `nextLotNumber()` (auto-increment style), `rateForVehicleType()`,
`parkedEntries()` (entries still `parked`, used for per-type occupancy telemetry).

Relationships: `floors()` (ordered), `slots()` (hasManyThrough lot → floor → slot),
`parkedEntries()` (hasMany, scoped to `status = parked`).

### 5.4 `ParkingFloor` (`app/Models/ParkingFloor.php`)

A level within a lot with a `slot_count`. `syncSlots()` provisions/backfills the actual
slot rows (defaulting new slots to 4W) whenever the floor is created or its slot count
grows. Relationships: `lot()` (belongsTo), `slots()` (hasMany, ordered by number).

### 5.5 `ParkingSlot` (`app/Models/ParkingSlot.php`)

One bay. `is_occupied` is the live sensor state; `vehicle_type` is the operator-assigned
2W/4W designation. `displayLabel()` shows the custom `label` (e.g. "A1") or falls back to
the slot number. Relationship: `floor()`.

### 5.6 `Kiosk` (`app/Models/Kiosk.php`)

A gate terminal bound to a lot. `key` is a stable, human-typed identifier
(`slug(name)-4chars`) used in the kiosk URL (`?kiosk=<key>`) and as an Echo channel name.
`makeKey()` generates it. Relationship: `parkingLot()`.

`type` is the gate's traffic direction — `Kiosk::TYPE_ENTRY` (admits vehicles, the only
place a card can be enrolled) or `Kiosk::TYPE_EXIT` (releases vehicles and charges the
fee). This is what removed the "arm a direction before scanning" step: the kiosk *is* the
direction, so a scan's meaning is fixed by where it is made. Nullable so a kiosk added
before the column existed is refused at scan time rather than guessing; helpers
`isEntry()` / `isExit()` read it. Constants live here rather than in an enum because the
column is a plain string in SQLite and MariaDB alike.

### 5.7 `User`

Standard Laravel auth scaffold; no routes currently use authentication.

---

## 5A. Middleware

### `ResolveKioskBinding` (`app/Http/Middleware/ResolveKioskBinding.php`)

Appended to the `web` group in `bootstrap/app.php`, so it runs for full page loads *and*
Livewire update requests. It decides which kiosk a browser is bound to and mirrors that
binding into the session (`kiosk_key` + `kiosk_owner`) and a pair of long-lived cookies
(`parkeasy_kiosk` + `parkeasy_kiosk_owner`, one year, refreshed on each visit, `HttpOnly` +
`secure` so the key is never readable from JS).

Resolution order is query string → session → cookie, narrowest scope first. An explicit
`?kiosk=` always wins, so binding or moving a terminal is just a new URL and nothing needs
clearing first.

Only a kiosk row that actually exists is remembered, at every level: an unrecognised key — a
typo, or a kiosk deleted since it was stored — falls through to the next source rather than
being reported as unbound. Otherwise a stale session entry would mask a working cookie.

**Two stores, two questions.** The session lasts as long as the visit or the login; the
cookie lasts a year, and is the only thing that survives a restart. Neither is redundant:

- A gate tablet has only a long-lived login, and `remember` gives that login a year too, but
  the two are not the same thing: the session ends at the browser restart while the cookie
  outlives it. A kiosk sits idle between shifts, so losing the binding costs a physical trip
  to the gate — which is why an operator's tablet gets both.
- A signed-in browser wants the opposite. The selection should last exactly as long as the
  login, and a login is *narrower* than a browser profile — so the cookie would keep it
  pinned for a year after sign-out and dump whoever used that machine onto a gate view. The
  session is the correct scope, and it ends at the logout.

**Who gets the long-lived cookie.** Only a browser that *is* the gate: an operator's tablet.
Staff who reach `?kiosk=` did so by clicking a link in the admin kiosk list, on their own
machine, so their selection is written to the session only and their cookie is actively
cleared rather than merely left to expire. That also un-pins laptops bound to a gate before
this rule existed. The clear is `ResolveKioskBinding::forgetCookie()`, deliberately *not*
`release()`: it must not take the session key with it, or a signed-in user would be un-pinned
the moment they signed in.

There is no third arm for a signed-out browser. The terminal is behind `auth`, so there is no
anonymous gate left to keep bound — and because this middleware is appended to the whole
`web` group, it also runs for the public dashboards. A year-long gate cookie planted from
`/lots` would outlive whatever the browser did next, so a guest gets the cookie cleared like
any other non-operator rather than planted.

**Whose binding it is.** Both stores also carry the id of the account that made the binding,
because a *lifetime* and an *owner* are different questions and the two stores only ever
answered the first. A gate tablet outlives the login — that is what the year-long cookie is
for — but it also outlives the account, and the next person to sign in on it may be at a
different lot. Without the owner stamp, two shifts sharing one tablet left the second standing
at the first one's barrier: for an operator, a 403 on every page; for a lot admin, nothing at
all, because staff are never authorised against a kiosk, so they simply got another lot's
terminal with its scans, its feed and no picker on screen to say so.

So a remembered binding that names somebody else is *released* rather than adopted, and the
terminal falls back to offering that account its own gates. Three cases are deliberately not
refused:

- a signed-out browser, which cannot be told what it is not entitled to until somebody signs
  in — and that request is the next one through the middleware, so the operator who followed a
  printed QR link is put back at the gate rather than being logged out of it;
- an **ownerless** binding, which is nobody's claim: a `?kiosk=` link followed while signed out,
  or a tablet carrying a cookie from before the stamp existed;
- a gate in a lot the incoming account administers, which is re-stamped for them. The gate
  belongs to the tablet, not the shift, so a handover between two operators — or a lot admin
  covering a shift on their own lot's tablet — must not cost a re-bind.

That third case is asked of `administeredLotIds()`, **not** of `can('operate')` or
`can('view')`. Both of those are answered `true` for the super admin by `Gate::before`, and a
break-glass account is attached to no lot at all, so it says nothing about whether the terminal
it happens to be holding is its gate. Read that way, a super admin inherited whatever the last
person on the tablet was standing at: no error, no 403, the picker hidden because something was
bound, and Unbind as the only route to any other gate. A `null` lot list is that account, so
"no restriction" is the one answer that is *not* a handover — and a super admin reaches a gate
the same way as anyone else, by opening its `?kiosk=` URL.

An explicit `?kiosk=` is never owner-checked: it carries no owner and is adopted outright, so
re-binding a terminal is still just opening the other URL, and a super admin previewing a
delinked kiosk does not lose it to the release rule.

Because the middleware is appended to the `web` group it runs *before* route middleware, so it
also resolves and remembers a `?kiosk=` link on a browser that is currently signed out. That
binding is then what puts the operator back at their own gate after they sign in, and the
mechanism is `User::landingUrl()` rather than `redirect()->intended()`: `/` no longer carries
an `auth` middleware, so nothing stores an intended URL for the landing-page hop, and an
operator is sent to `/?kiosk=<key>` by role instead — to the single operable gate if they have
one, and to the picker if they have several. (The `/admin/*` routes do still use `auth`, so
`intended()` still carries an admin deep link across a login.)

The decision is `User::operatesKiosks()`, which answers from the **role** and never from
`can('kiosks.operate')`. `Gate::before` waves the super admin through every check, so a
permission test there would hand the break-glass account the same treatment as gate staff and
quietly bind the super admin's own laptop to a gate.

An operator is additionally held to their own lot: `Gate::authorize('operate', $kiosk)` runs
whenever the kiosk came from the query string on a request with an operator on it, so another
lot's kiosk is a 403 rather than a page that silently renders unbound. A binding *inherited*
from the previous shift never reaches that check — it is released above, because nobody asked
for it, and a 403 would be the wrong answer to a question this account did not pose.

`ForgetKioskController` (`GET /kiosk/forget`) is the manual escape hatch for a shared machine
that was bound once; it delegates to `release()`, which clears both stores, and because
`release()` queues onto the response cookie jar it lands *after* the middleware, so the
clearing response cannot re-plant the cookie it is removing.

The "Not this kiosk? Unbind" link is rendered for **any account bound to a kiosk**, not for
operators only. The two conditions were being conflated: `operatesKiosks()` answers "may this
person run a gate?", which is an authorisation question, while clearing the binding is a
local-state reset. The route was public throughout and mutated nothing but the caller's own
binding, so the link was never hiding a boundary — it was gating a utility on a role.

Asking the role instead offered the escape hatch to the person least likely to need it. A lot
admin covering a shift at a gate on a tablet, because the lot has no dedicated operator, was
shown no way off a bound gate at all.

It always returns to `/`. Unbinding is not "go and manage kiosks", it is "stop showing me this
gate", so the answer is the page you were just looking at — unbound, where the way this account
picks a kiosk is offered again. `landingUrl()` is never used here: for an operator with a single
operable kiosk it returns `/?kiosk=<key>`, which would re-bind them one redirect later and turn
the button into a silent no-op.

Staff used to be sent to `/admin/kiosks` instead, which was the one destination that made this a
detour — it dropped you out of the terminal to reach a page that names kiosks but shows no gate,
so answering "wrong gate" meant leaving the terminal and coming back. Sending everyone to `/`
removes a role from the redirect entirely, and is only possible because the terminal now offers
staff a kiosk dropdown of their own (see §7.1).

### 5B. `EnsureCanUseAdminPanel` (`app/Http/Middleware/EnsureCanUseAdminPanel.php`)

Applied to the whole `/admin/*` group in `routes/web.php`, alongside `auth`.

This is the *page* boundary, not the security boundary. Each admin component calls
`Gate::authorize` itself — in `mount()` and before every mutating action — and those calls
run per action, so they also cover Livewire update requests, which do not pass through route
middleware at all. What this adds is a decision before the component renders: without it an
account with no admin role passes `auth` and the only thing that stops it is a 403 from
inside the component, having already rendered the shell of a page it cannot use.

- Staff (`User::canUseAdminPanel()`) pass through.
- An **operator** is redirected to their kiosk page. A 403 on a link the navigation never
  shows them tells them nothing, and running a gate is the entire job.
- Everyone else is refused with 403, exactly as before. An account that is neither staff nor
  an operator has no business here, and redirecting it somewhere would only hide that.

`canUseAdminPanel()` replaced an inline `isSuperAdmin() || isLotAdmin()` in
`ParkingLotPolicy::viewAny` and now serves three callers: the policy, this middleware, and
the sign-in redirect. It stays role-based rather than permission-based so the staff set is
unchanged — the panel was never reachable on permissions alone.

---

## 5C. Roles and permissions

`app/Models/Access.php` is the whole catalogue: one file so the seeder, the policies and the
navigation cannot drift apart. `sync()` creates every permission from `allPermissions()` — a
union of the three role sets — because a permission granted only to a lesser role
(`kiosks.operate` is exactly that) would otherwise never get a row, and `syncPermissions()`
would fail on a name with nothing behind it.

| Permission | operator | lot admin | super admin |
|---|---|---|---|
| `kiosks.operate` | ✓ | | ✓ |
| `lots.manage` | | | ✓ |
| `floors.manage` | | ✓ | ✓ |
| `slots.manage` | | ✓ | ✓ |
| `kiosks.manage` | | ✓ | ✓ |
| `vehicles.view` | | ✓ | ✓ |
| `vehicles.create` | | ✓ | ✓ |
| `vehicles.update` | | | ✓ |
| `vehicles.delete` | | | ✓ |
| `users.manage` | | | ✓ |

`kiosks.operate` is deliberately a separate ability from `kiosks.manage`. The latter is the
right to *configure* kiosks — their lot, their gate type, their key — and belongs to lot
admins. The former is only the right to stand at a gate and scan, and is granted to nobody
else, so the two cannot be confused when `KioskPolicy` reads. `KioskPolicy::operate()` checks
both halves: the permission, and `administersLot($kiosk->parking_lot_id)`.

Lot scoping needs no per-role rule. `User::administeredLotIds()` returns `null` only for the
super admin and otherwise reads the `user_parking_lot` pivot, so an operator inherits the
boundary from existing code — and an operator with no assignment gets an empty array, which
means `where 0 = 1` rather than an unfiltered list.

Two things assign these roles. `AdminUserSeeder` provisions one lot admin **and** one operator
per lot from a single loop over the lots (`lot-<n>-admin@…`, `lot-<n>-operator@…`), because two
hand-written loops over the same lots are where the two would drift and quietly hand one
person's gates to another. Each lot-scoped account defaults its password to its own address, so
the credentials printed at the bottom of a fresh seed are the ones the address already implies.
Beyond seeding, `Admin\UserManager` (`/admin/users`, gated on `users.manage`, so super admin
only) creates and edits accounts by hand — see §7.7.

**Not enforced:** `/api/rfid-scan` and `/api/rfid-scan/enrol` take the kiosk key in the body
and are unauthenticated. The operator role bounds what an account can *see and open*, not
what can be posted to the scan endpoints. Closing that is a separate piece of work (see §10).

---

## 6. Controllers

### 6.1 `RfidScannedController` (`app/Http/Controllers/RfidScannedController.php`)

The engine of the entry/exit system.

**`rfidScanned(Request)`** (POST `/api/rfid-scan`)

1. Validates `rfid_id`, required `lot`, required `kiosk`. There is no `type` in the body:
   the client does not get to choose the direction.
2. Looks up the `ParkingLot` by `lot_number` (404 if unconfigured).
3. `rejectIfKioskNotLinked()` — verifies the kiosk key matches the reported lot (refuses
   silently if the kiosk row is missing, broadcasts an error if it exists but targets
   another lot).
4. Reads the direction from `Kiosk::type`. A kiosk with no type is refused with 422 — a gate
   that has not been told whether it admits or releases could let a vehicle out uncharged.
5. Looks up the `Vehicle` by `rfid_id` (matched case-insensitively after normalising).
6. **Exit path** (`kiosk->isExit()`):
   - No active entry → 422 "No active entry for this card". An exit gate only releases, so
     it never admits, and an unknown card is never let out.
   - Active entry belongs to another lot → 422 "Vehicle is parked at a different lot"
     (a card can only leave from the lot it entered).
   - `closeEntry()` computes the fee, flips the entry to `exited`/`exit_time`/`amount`,
     records `exit_kiosk_key`, broadcasts `RfidScanned(status=exit, amount=…)`.
7. **Entry path** (`kiosk->isEntry()`):
   - Unknown card → broadcasts `enrolment_required` and returns it, so the kiosk collects
     the driver/vehicle details once.
   - Already parked → 422 "Vehicle is already parked".
   - Otherwise creates the `parked` entry, records `entry_kiosk_key`, and broadcasts.

Both paths hold a `lockForUpdate()` on the vehicle row across the check-then-create so a
double-tapped kiosk cannot open two visits for one card.

**`enrol(Request)`** (POST `/api/rfid-scan/enrol`)
For a first-time card. Validates the vehicle details, re-checks the lot + kiosk binding,
requires an ENTRY kiosk (an exit gate has no visit to attach the card to, so it is
refused), creates the `Vehicle` (unique `rfid_id`, catching the unique-index race where two
kiosks enrol the same card at once), then immediately creates a `parked` entry and
broadcasts. If the card was enrolled by another kiosk between the scan and the submit, it
falls through to the normal entry path instead of rebinding.

**`closeEntry()` / `calculateFee()`**
Fee = `max(1, ceil(entry_time→exit_time in minutes / 60)) × rate`. Minutes round up to at
least one full hour; the rate comes from the entry's lot (`rateForVehicleType`) keyed off
the entry's `vehicle_type`, defaulting to the 4W rate for legacy rows (and hard-defaults of
10/20 rupees for unconfigured rates).

**`rejectIfKioskNotLinked()`**
- No `kiosk` in payload → allowed (a reader that hasn't learned its key yet).
- Kiosk row exists but `parking_lot_id` is null → 422 “Kiosk is not linked to a parking lot”.
- Kiosk row missing or bound to a different lot → broadcasts an error to that kiosk and
  returns 422 “Kiosk is not linked to this parking lot”.

### 6.2 `SlotStatusController` (`app/Http/Controllers/SlotStatusController.php`)

The ingestion point for the IR sensor hardware.

**`update(Request)`** (POST `/api/slot-status`)

1. Validates `lot`, `floor`, `slot` (all integers ≥ 0/1).
2. Resolves lot by `lot_number`, then the floor within that lot by `floor_number`.
   Returns 404 with an operator-friendly message if either is unconfigured.
3. Returns 404 if `slot` exceeds the floor's `slot_count`.
4. `firstOrCreate`s the `ParkingSlot` row for that floor+number (so late-created rows are
   tolerated), then **toggles** `is_occupied` and stamps `last_updated_at`.
   - Consecutive hits alternate free → occupied → free → … The API deliberately does **not**
     take a status field; each hit is a blind flip, matching the “beam broken / beam
     restored” nature of a simple IR unit. (This is also a known weakness — a missed event
     permanently desyncs a slot; see `PITCH.md` for the planned state-based telemetry.)
5. Reloads `floor.lot`, broadcasts `SlotStatusChanged` (synchronously, on `parking-slots`),
   and returns the new state with a full label context.

---

## 7. Livewire components

All components are **class-based** (Livewire 4). They render the Blade views under
`resources/views/livewire/`.

### 7.1 `Home` — the landing page and the kiosk terminal (`/`)

- Renders **two** views from one component. `render()` returns `livewire.landing` when
  `auth()->guest()` — a static page, no queries, no interactivity — and `livewire.home`
  otherwise. The branch is where the login boundary lives (see the actors section), so it has
  to come first: nothing above it may touch a model, or the guest path would start running
  kiosk queries for a page that shows none.
- The bound `Kiosk` (and its lot) is resolved by `ResolveKioskBinding` middleware before the
  component runs, from the `?kiosk=` query string, else the remembered cookie. If no
  real kiosk resolves, the page warns the attendant.
- Two ways to pick a kiosk, for two different roles, and deliberately not interchangeable.
  Neither control exists once a kiosk is bound — the terminal's job is to be one gate, and
  `/kiosk/forget` is the way back to a choice:
  - `gateChoices()` — the full-page **"Choose your gate"** card picker, **operators only**,
    shown *instead of* the terminal when no kiosk is bound. Entry-or-exit is a
    start-of-shift decision, the answer has to reach the binding, and a barrier tablet wants
    large targets.
  - `pickableKiosks()` — a compact **kiosk dropdown** above the ENTRY/EXIT panes, for
    anyone holding `kiosks.manage`. Staff pick which gate to *look at*, not which shift they
    are working, so the terminal stays on screen behind the control. Gated on the
    permission rather than the role so a role granted `kiosks.manage` gets it without a
    second place to remember to update; `runnableKiosks()` is unbounded for a super admin,
    which is why the gate on it matters.
  - Kiosks that **cannot scan** — no lot, or no gate type — stay in the list, labelled
    `no lot` / `no gate type`. They are what an admin opens the terminal to diagnose, and
    hiding them would replace one dead end with a quieter one.
  - Selecting navigates to `/?kiosk=<key>` as a plain GET. A Livewire round trip would be the
    wrong tool: the binding is recorded by `ResolveKioskBinding` on the request, and choosing
    a gate rebinds the lot, the broadcast channel and the scan direction at once.
- The unbound state says what is actually missing rather than blaming a kiosk that was never
  chosen. `scanBlocked()` in the view's JS is the single source of that answer for the
  keyboard buffer, the manual field and `submitScan()`, checked most-fundamental first
  (no kiosk → no lot → no gate type); each hint branch owns its own baseline, so a timer
  cannot restore "Scan your card" onto a terminal that has no kiosk.
- Passes `kioskKey`, `kioskType`, `kioskLotNumber`, `kioskName`, `kioskLotName`, and a
  `recentScans` list (persisted entries for that lot, newest first, up to 6 rows combining a
  `parked` and, if present, an `exit` event per entry — so the feed survives page refresh).
- The Blade view's vanilla JS drives the UX: the ENTRY/EXIT panes are **indicators, not
  controls** — the registered `kioskType` lights one up and greys the other, and nothing has
  to be armed before scanning. On top of that: keyboard/USB-RFID buffer for scan input,
  manual card input, result overlay, a first-time enrolment form
  (`/api/rfid-scan/enrol`), and **Echo listening on `kiosk.<key>`** so hardware scans
  arrive over WebSockets with zero polling. Dedup keys (`entry_id:status`) prevent the
  API response and the broadcast from double-adding feed rows.
- Live feed rows are cloned from a server-rendered `<template id="recent-row-template">`
  rather than built as a JS string, so a field added in Blade cannot go missing on rows
  created after page load.

### 7.2 `LotOverview` — live lot report (`/lots`)

- `search` (live) and `sortBy` (`free` / `occupancy` / `lot`) bound with `wire:model.live`.
- `render()` fetches lots with eager `withCount` aggregates: total slots, occupied slots,
  and per-type totals + occupied counts for 2W/4W. It only lists lots **with configured
  slots** (`whereHas('slots')`) so empty/unconfigured lots don't clutter the map-by-search
  page.
- Sorts in PHP (collection), marks the best pick (most free) card.
- The view uses `wire:poll.10s` for periodic refresh — Livewire re-renders while the IR
  broadcasts keep the *slot monitor* instantaneous; this page is the poll-based companion.

### 7.3 `SlotDashboard` — slot monitor (`/slots`)

- `mount()` defaults to the lowest `lot_number`.
- `render()` loads the chosen lot's floors with slots (ordered), and computes
  `totalSlots`, `occupiedSlots`, `freeSlots`, `occupancyPct` for the stats cards.
- Each slot is a tile carrying `data-lot/floor/slot/occupied/type/updated` attributes.
  The view's script subscribes to Echo `parking-slots` (`SlotStatusChanged`) and flips the
  matching tile's class/icon/label, then recomputes the stats and flashes the “Live ·
  listening” chip. This is the “green tile → red tile” demo moment.

### 7.4 `Admin\LotManager` — parking lots CRUD (`/admin/lots`)

- Form to add a lot (`name`, `address`, `rateTwoWheeler=10` default, `rateFourWheeler=20`
  default). `nextLotNumber()` auto-assigns `lot_number`.
- Edit (inline) and delete (confirm-then-delete) flows; `render()` shows each lot with
  its floor/slot counts via `withCount`. Uses `Livewire\WithPagination` trait.

### 7.5 `Admin\FloorManager` — floors & slots (`/admin/floors`)

- Two independent concerns kept in separate properties:
  - **Add/edit floor**: `lotId`, `name`, `slotCount` (1–500). Floor numbers are
    auto-assigned as `max(floor_number)+1` within the chosen lot; `syncSlots()` provisions
    the slot rows immediately.
  - **List filter**: `#[Url(as: 'lot')] filterLotId` scopes the floors list to one lot via
    `?lot=<id>` (e.g. from the “Floors & Slots” button on the lot report), independent of
    the add-form selection.
- **Slot type designation**: tapping a slot chip stages a change in `pendingTypeChanges`
  (2W ⇄ 4W) without persisting; a floating bar offers **Apply** (`confirmTypeChanges`),
  **Discard** (`discardTypeChanges`), and a second tap on a chip reverts a staged change.
  Persisting fires `dispatch('floor-saved')`.
- Delete floor has an inline confirm modal. `render()` groups floors by lot unless scoped.

### 7.6 `Admin\KioskManager` — kiosks (`/admin/kiosks`)

- Add a kiosk (name + lot + **gate type**) → `Kiosk::makeKey()` generates the unique key. The
  gate type is required on add and edit, and is shown as `ENTRY GATE` / `EXIT GATE` on each
  row so an operator can see at a glance which way each terminal faces.
- Edit name/lot/type, **delink** (`parking_lot_id = null`), delete, and shows each kiosk's lot
  with floor/slot counts — the operator copies the `/?kiosk=<key>` URL for each terminal.
- "Open kiosk" links to `/?kiosk=<key>`. Staff who already have the terminal open pick a gate
  with the **kiosk dropdown** in §7.1 instead of coming back here — and once bound, that
  dropdown is gone, so this button and Unbind are the two ends of the round trip.
- `render()` loads kiosks eager-loaded with their lot.

### 7.7 `Admin\UserManager` — staff accounts (`/admin/users`)

- An account is **one role plus a set of lots**, and the two are not independent: a lot admin or
  an operator reaches exactly the lots they are attached to, so the lot picker decides what a
  person can actually touch. One role per account rather than a set — the role decides the whole
  reach, so a second role would be either inert or a privilege escalation waiting for someone to
  notice the order it was applied in.
- The picker is scoped through `ScopesToAdministeredLots::administeredLots()`, so it can only
  *offer* the caller's own lots, and every submitted id is re-checked by
  `authorizedLotIds()` — which aborts on a foreign id rather than silently narrowing the save.
- A `super_admin` account is never attached to a lot: its reach is the role, so a pivot row
  would be inert and would make the navbar show one lot for an account that administers all.
- The navbar badge names the account's lowest-numbered lot and counts the rest, so the lots
  are ordered by `lot_number` rather than left to the join. Row id is the order lots happened
  to be created in, which is arbitrary — it would change what a lot admin is shown for no
  reason connected to their lot. The mobile panel reuses the same list rather than re-querying,
  so the two renderings cannot disagree about order.
- Editing leaves the password alone unless a new one is typed. Pre-filling it would put the
  existing password in the DOM; requiring it would force a reset on every unrelated role change.
- Two checks are made in the component rather than left to `UserPolicy`, because `Gate::before`
  answers `true` for a super admin before any policy method is reached — so a policy clause can
  never stop the break-glass account from locking everyone out:
  - a super admin cannot change their own role;
  - nobody can delete their own account (asserted on both `confirmDelete` and `delete`).
- `UserPolicy`'s clauses still earn their place for any lesser role later granted
  `users.manage`, so they are kept rather than deleted.

---

## 8. Events & real-time data flow

### 8.1 `RfidScanned` (`app/Events/RfidScanned.php`)

- Implements `ShouldBroadcastNow` (`broadcastQueue = 'null'`) → synchronous push.
- Payload: `rfid_id, status (parked|exit|enrolment_required|error), message,
  entry_id, amount, vehicle_number, driver_name, kiosk, lot, vehicle_type`.
- `broadcastOn()`: `kiosk.<kioskKey>` when a kiosk key is known; else `kiosk.<lot>` when a
  lot is known; else the global `rfid` channel. Broadcast using the `broadcast()` helper.

### 8.2 `SlotStatusChanged` (`app/Events/SlotStatusChanged.php`)

- Flattens the slot's context into scalars: `slot_id`, `lot_number`, `lot_name`,
  `floor_number`, `floor_name`, `slot_number`, `is_occupied`, `last_updated_at`.
- Broadcasts synchronously on the public `parking-slots` channel; every viewer's monitor
  tile updates serverlessly.

### 8.3 End-to-end flows

**Entry of a registered card**
1. Card scanned at an ENTRY kiosk (hardware keyboard-buffer or manual) →
   `POST /api/rfid-scan {rfid_id, lot, kiosk}`. No direction is sent: the kiosk's type is
   the direction.
2. Controller resolves vehicle, finds no active entry → creates `Entry` (status `parked`),
   records `entry_kiosk_key`, broadcasts `parked` on `kiosk.<key>`.
3. Kiosk shows “Vehicle Parked” and the row appears in the feed.

**First-time visitor**
- Step 1 returns `{status:'enrolment_required'}`; kiosk opens the enrolment overlay →
  `POST /api/rfid-scan/enrol` → creates `Vehicle` + immediately a `parked` `Entry`. ENTRY
  kiosks only.

**Exit**
1. Card scanned at an EXIT kiosk → `POST /api/rfid-scan {rfid_id, lot, kiosk}`.
2. Controller validates active entry + same-lot, computes fee, marks entry `exited`,
   records `exit_kiosk_key`, broadcasts `exit` with `amount`, kiosk shows “Fee: ₹X”.

**Wrong gate**
- An EXIT kiosk never admits: an unparked card is refused with “No active entry for this
  card” and no visit is created. An ENTRY kiosk never releases. A kiosk with no type is
  refused outright rather than guessing, because guessing could release a vehicle without
  charging it.

**Slot occupancy**
1. A car enters bay `(lot 1, floor 0, slot 3)` → ESP32 `POST /api/slot-status` once.
2. Controller toggles the slot to occupied, broadcasts `SlotStatusChanged`.
3. The slot monitor tile flips red and the stats re-tally live; the lot report reflects
   the change on its next 10s poll.

---

## 9. Running the application

```sh
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed        # seeds lots, floors, slots, kiosks, test user
npm install && npm run build

# Backend + Reverb (dev):
php artisan serve
php artisan reverb:start
```

- Open a kiosk at `http://localhost:8000/?kiosk=police-bazaar-entry` (keys seeded in
  `KioskSeeder`: `police-bazaar-entry`, `police-bazaar-exit`, `tura-bus-stand-entry`, …).
  The key is the kiosk's identity, so renaming one orphans anything pointing at it.
- Admin: `/admin/lots` → `/admin/floors` → `/admin/kiosks` (in that order).
- Staff accounts: `/admin/users` (super admin only). A seeded lot gets both a
  `lot-<n>-admin@…` and a `lot-<n>-operator@…` account; the operator signs in at `/login` and
  lands straight on that lot's gate.
- Simulate a sensor with `curl -X POST http://localhost:8000/api/slot-status \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"lot":1,"floor":0,"slot":3}'` — each call toggles the slot, and the monitor updates live.
- Tests: `composer test` (PHPUnit; uses SQLite).

---

## 10. Known limitations / roadmap pointers

- **The scan API is still unauthenticated.** `/api/rfid-scan` and `/api/rfid-scan/enrol`
  accept a kiosk key in the body from any caller. Putting `/` behind a login did not change
  this, and should not be read as having done so: the terminal page's own scans go through
  these same open endpoints, so requiring a session cookie would only stop the legitimate
  caller. The `operator` role (see §5C) bounds what an account can see and open; it does not
  bound what can be posted. Closing this is the remaining half of the hardening item in
  `PITCH.md`, and it needs a per-device token rather than a user session — the endpoint looks
  like it is also meant to serve an ESP32 reader (see §3.2), which cannot log in.
- **IR toggling is a blind flip** — a dropped event desyncs a slot until the next toggle;
  the roadmap is state-based telemetry + heartbeat/timeout reconciliation.
- **Fee is computed, not collected** — payment (UPI/card) is the primary roadmap feature.
- **Single point of failure** at the central server + Reverb; kiosk-side retry queue is
  planned.
