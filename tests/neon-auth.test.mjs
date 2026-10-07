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


test('login shows progress, blocks repeated submissions and restores the form on failure', async () => {
    const originalDocument = globalThis.document;
    const originalLocation = globalThis.location;
    try {
        const attributes = new Map();
        const classes = new Set();
        const button = { disabled: false, textContent: 'Entrar no escritório', innerHTML: 'Entrar no escritório',
            setAttribute: (key, value) => attributes.set(key, value), removeAttribute: key => attributes.delete(key),
            classList: { add: name => classes.add(name), remove: name => classes.delete(name) } };
        const error = { hidden: true, textContent: '' };
        const status = { hidden: true, textContent: '' };
        const toggle = { disabled: false, addEventListener() {} };
        const recover = { disabled: false, addEventListener() {} };
        let submit;
        const form = { elements: { email: { value: 'test@example.test' }, password: { value: 'test-password' } },
            querySelector: selector => selector === '.form-error' ? error : button,
            addEventListener: (event, callback) => { submit = callback; }, setAttribute() {}, removeAttribute() {} };
        globalThis.location = { search: '' };
        globalThis.document = { querySelector: selector => ({ '#login-form': form, '#toggle-signup': toggle,
            '#recover-account': recover, '#auth-status': status }[selector] || null) };
        let reject;
        let calls = 0;
        await setupNeonAuth({ toast() {}, api: () => { calls++; return new Promise((resolve, fail) => { reject = fail; }); } });
        const pending = submit({ preventDefault() {} });
        assert.equal(button.textContent, 'Entrando…');
        assert.equal(button.disabled, true);
        assert.equal(status.hidden, false);
        assert.equal(attributes.get('aria-busy'), 'true');
        assert.equal(classes.has('is-loading'), true);
        await submit({ preventDefault() {} });
        assert.equal(calls, 1);
        reject(new Error('Confira seu e-mail e senha.'));
        await pending;
        assert.equal(button.disabled, false);
        assert.equal(status.hidden, true);
        assert.equal(error.hidden, false);
        assert.equal(error.textContent, 'Confira seu e-mail e senha.');
        assert.equal(form.elements.password.value, 'test-password');
        assert.equal(button.innerHTML, 'Entrar no escritório');
        await setupNeonAuth({ toast() {}, api: async () => ({ redirect: '/painel' }) });
        await submit({ preventDefault() {} });
        assert.equal(button.textContent, 'Abrindo seu escritório…');
        assert.equal(button.disabled, true);
        assert.equal(status.hidden, false);
        assert.equal(globalThis.location.href, '/painel');
    } finally {
        globalThis.document = originalDocument;
        globalThis.location = originalLocation;
    }
});
