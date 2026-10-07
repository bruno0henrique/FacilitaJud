import assert from 'node:assert/strict';
import { test } from 'node:test';
import { setupNeonAuth } from '../resources/js/neon-auth.js';

test('renews expiring sessions after navigation without duplicate calls or demo traffic', async () => {
    const originalDocument = globalThis.document;
    const originalLocation = globalThis.location;
    const originalInterval = globalThis.setInterval;
    try {
        let active = '0';
        let visibility;
        let interval;
        let calls = 0;
        let finish;
        globalThis.location = { search: '' };
        globalThis.document = {
            hidden: false,
            querySelector: selector => selector.includes('neon-session-active') ? { content: active }
                : selector.includes('neon-session-expires-at') ? { content: String(Math.floor(Date.now() / 1000) + 60) } : null,
            addEventListener: (event, callback) => { visibility = callback; },
        };
        globalThis.setInterval = callback => { interval = callback; };
        const options = { currentModule: 'painel', toast: () => {}, api: () => {
            calls++;
            return new Promise(resolve => { finish = resolve; });
        } };
        await setupNeonAuth(options);
        assert.equal(calls, 0);
        assert.equal(interval, undefined);
        active = '1';
        await setupNeonAuth(options);
        assert.equal(calls, 1);
        visibility();
        await interval();
        assert.equal(calls, 1);
        finish({});
        await Promise.resolve();
        visibility();
        await interval();
        assert.equal(calls, 1);
    } finally {
        globalThis.document = originalDocument;
        globalThis.location = originalLocation;
        globalThis.setInterval = originalInterval;
    }
});
