/**
 * Contract test for the kiosk's live "recent scans" rows.
 *
 * The bug this guards against: the row markup used to exist twice, once in Blade and once
 * as a JS string inside pushRecent(). When the kiosk tag was added to Blade it never
 * reached the live rows, so a scan made after page load rendered with fields missing while
 * server-rendered rows looked correct. Live rows are now cloned from the server-rendered
 * template, so these tests assert a real live scan carries every field.
 *
 * Each test evaluates the page's real inline script against a jsdom document and drives it
 * the way a user does: type a card, press Enter. That goes through submitScan -> the API ->
 * pushRecent, so the shipped code is what is under test. There is no direction to pick: the
 * page is booted against an entry or an exit kiosk and the gate type decides the outcome.
 *
 * Run with: node --test tests/js/
 * Needs the dev server up (KIOSK_URL, default https://parkeasy.test).
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const APP_URL = process.env.KIOSK_URL ?? 'https://parkeasy.test';
const LOT = Number(process.env.KIOSK_LOT ?? 1);
const ENTRY_KIOSK = process.env.KIOSK_ENTRY ?? 'police-bazaar-entry';
const EXIT_KIOSK = process.env.KIOSK_EXIT ?? 'police-bazaar-exit';

const post = (path, body) =>
    fetch(`${APP_URL}${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
    });

/**
 * Load the kiosk page and evaluate its own inline script, with timers stubbed so the
 * clock interval and the Echo retry loop can't keep the test process alive.
 */
async function bootKiosk(kiosk) {
    const html = await (await fetch(`${APP_URL}/?kiosk=${kiosk}`)).text();

    assert.ok(html.includes('recent-row-template'), 'kiosk page is missing the row template');

    const dom = new JSDOM(html, {
        url: `${APP_URL}/?kiosk=${kiosk}`,
        runScripts: 'outside-only',
        pretendToBeVisual: true,
    });

    const { window } = dom;

    window.setInterval = () => 0;
    window.setTimeout = () => 0;
    window.clearInterval = () => {};
    window.clearTimeout = () => {};

    // Let the page script talk to the real dev server rather than jsdom's null origin.
    window.fetch = (input, init) =>
        fetch(typeof input === 'string' && input.startsWith('/') ? `${APP_URL}${input}` : input, init);

    const inline = [...window.document.querySelectorAll('script')]
        .map((s) => s.textContent)
        .find((s) => s.includes('pushRecent'));

    assert.ok(inline, 'could not find the inline kiosk script');

    window.eval(inline);

    return window;
}

/** Type a card and press Enter, exactly as an attendant would. */
async function scanInBrowser(window, card) {
    const input = window.document.getElementById('manual-card');
    input.value = card;
    input.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

    // The scan is a real HTTP round trip; wait for the row to land.
    const deadline = Date.now() + 8000;
    while (Date.now() < deadline) {
        await new Promise((r) => setTimeout(r, 50));

        const row = window.document.getElementById('recent-list').firstElementChild;
        if (row && row.getAttribute('data-card-code') === card.toUpperCase()) {
            return row;
        }
    }

    throw new Error(`scan for ${card} never appeared in the feed`);
}

const slot = (row, name) => row.querySelector(`[data-slot="${name}"]`);

const slotText = (row, name) => {
    const el = slot(row, name);
    return el ? el.textContent.trim() : null;
};

/** Release any visit left open for a card, so a rerun starts clean. */
async function clearVisit(card) {
    await post('/api/rfid-scan', { rfid_id: card, lot: LOT, kiosk: EXIT_KIOSK });
}

/**
 * Run a test against a card that is parked for the duration, then release it.
 *
 * Each test uses its own seeded card: a card holds only one open visit, so sharing one
 * would make the tests interfere with each other.
 *
 * `fn` is called with the card; if it needs the vehicle already parked, `prePark` says so.
 * Entry tests leave that to the browser scan itself, otherwise the scan would be refused
 * with "Vehicle is already parked" and no row would ever be built.
 */
async function withCard(card, fn, { prePark = false } = {}) {
    await clearVisit(card);

    try {
        if (prePark) {
            const entry = await (await post('/api/rfid-scan', {
                rfid_id: card,
                lot: LOT,
                kiosk: ENTRY_KIOSK,
            })).json();

            assert.equal(entry.status, 'parked', `pre-park failed: ${JSON.stringify(entry)}`);
        }

        return await fn(card);
    } finally {
        await clearVisit(card);
    }
}

test('a live entry scan renders every field, including the kiosk', async () => {
    const window = await bootKiosk(ENTRY_KIOSK);

    // RFID-0001 is seeded; sent lowercase on purpose to also cover card normalisation.
    await withCard('RFID-0001', async () => {
        const row = await scanInBrowser(window, 'rfid-0001');

        assert.equal(row.getAttribute('data-scan-status'), 'parked');
        assert.equal(row.getAttribute('data-kiosk'), ENTRY_KIOSK, 'live row is missing data-kiosk');
        assert.equal(slotText(row, 'status'), 'Parked');
        // Seeded plate for RFID-0001; asserted rather than hardcoded from the scan so a
        // seeder change surfaces here instead of silently passing.
        assert.equal(slotText(row, 'vehicle-number'), 'ML01AB1234');
        assert.equal(slotText(row, 'card'), 'Card RFID-0001');
        assert.equal(
            slotText(row, 'kiosk'),
            ENTRY_KIOSK,
            'live row is missing the kiosk label (the bug this test guards against)',
        );
        assert.ok(slotText(row, 'time'), 'live row is missing its timestamp');
        assert.ok(slot(row, 'type-icon'), 'live row is missing the vehicle type icon');
        assert.equal(
            slotText(row, 'amount'),
            null,
            'an entry has no fee, so the amount slot should be absent',
        );
    });
});

test('a live exit scan renders the fee and the kiosk that handled it', async () => {
    const window = await bootKiosk(EXIT_KIOSK);

    // Parked first so the browser scan below is an exit rather than a second entry.
    await withCard('RFID-0002', async (card) => {
        const row = await scanInBrowser(window, card);

        assert.equal(row.getAttribute('data-scan-status'), 'exit');
        assert.equal(slotText(row, 'status'), 'Exit');
        assert.equal(
            row.getAttribute('data-kiosk'),
            EXIT_KIOSK,
            'the row must be attributed to the exit kiosk the scan was made on',
        );
        assert.equal(slotText(row, 'kiosk'), EXIT_KIOSK, 'live exit row is missing the kiosk label');

        const amount = slotText(row, 'amount');
        assert.ok(amount && amount.startsWith('₹'), `live exit row is missing the fee (got ${amount})`);
    }, { prePark: true });
});

test('a row with no kiosk key renders without a blank kiosk label', async () => {
    const window = await bootKiosk(ENTRY_KIOSK);

    await withCard('RFID-0003', async () => {
        await scanInBrowser(window, 'RFID-0003');
        // A row can still reach pushRecent without a kiosk label (a visit recorded before
        // kiosk attribution existed). It must render rather than showing an empty tag.
        window.__kioskTest.pushRecent('parked', 'NO-KIOSK', null, 'KA00AA0000', 'No Kiosk', null, 'four_wheeler', '');

        const noKiosk = window.document.getElementById('recent-list').firstElementChild;

        assert.equal(noKiosk.getAttribute('data-kiosk'), '');
        assert.equal(slotText(noKiosk, 'kiosk'), null, 'an empty kiosk must not leave a blank label');
        assert.equal(slotText(noKiosk, 'vehicle-number'), 'KA00AA0000');
    });
});

test('a live row carries every slot the server-rendered template defines', async () => {
    // The invariant behind the fix: one source of truth for the row markup. If a slot is
    // added to the template, a live row must end up with it too.
    const html = await (await fetch(`${APP_URL}/?kiosk=${ENTRY_KIOSK}`)).text();
    const window = await bootKiosk(ENTRY_KIOSK);

    const templateSlots = [...new JSDOM(html).window.document
        .getElementById('recent-row-template')
        .content.querySelectorAll('[data-slot]')]
        .map((el) => el.getAttribute('data-slot'))
        .sort();

    assert.ok(templateSlots.length > 0, 'the row template defines no slots');

    await withCard('RFID-0004', async () => {
        const row = await scanInBrowser(window, 'RFID-0004');

        const liveSlots = [...row.querySelectorAll('[data-slot]')]
            .map((el) => el.getAttribute('data-slot'))
            .sort();

        // 'amount' is legitimately absent on an entry; the rest must all be present.
        const expected = templateSlots.filter((s) => s !== 'amount');

        assert.deepEqual(liveSlots, expected, 'a live row is missing slots the template defines');
    });
});