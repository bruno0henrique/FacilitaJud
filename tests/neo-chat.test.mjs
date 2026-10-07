import test from 'node:test';
import assert from 'node:assert/strict';
import { readNeoStream } from '../resources/js/neo-chat.js';

test('SSE decodes fragmented UTF-8 characters and multiple events progressively', async () => {
    const bytes = new TextEncoder().encode('event: status\ndata: {"text":"Preparando"}\n\nevent: delta\ndata: {"text":"ação"}\n\nevent: done\ndata: {}\n\n');
    const seen = []; const body = new ReadableStream({ start(c) { for (const byte of bytes) c.enqueue(new Uint8Array([byte])); c.close(); } });
    await readNeoStream(body, (event, value) => seen.push([event, value]));
    assert.deepEqual(seen, [['status', {text:'Preparando'}], ['delta', {text:'ação'}], ['done', {}]]);
});
