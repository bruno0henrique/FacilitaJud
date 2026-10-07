import test from 'node:test';
import assert from 'node:assert/strict';
import { AudioUploadQueue } from '../resources/js/meeting-recorder.js';

test('splits audio into small ordered chunks and releases sent data', async () => {
    const received = []; const queue = new AudioUploadQueue(async chunk => received.push([chunk.sequence, chunk.blob.size]));
    await queue.append(new Blob([new Uint8Array(600000)]));
    assert.deepEqual(received, [[0,262144],[1,262144],[2,75712]]);
    assert.equal(queue.savedBytes,600000); assert.equal(queue.sentCount,3); assert.equal(queue.pending.length,0);
});

test('retains unsent audio and retries with the same sequence after failure', async () => {
    let fail = true; const received = []; const queue = new AudioUploadQueue(async chunk => { if (fail) throw new Error('offline'); received.push(chunk.sequence); });
    await assert.rejects(queue.append(new Blob(['first'])), /offline/);
    await assert.rejects(queue.append(new Blob(['second'])), /offline/);
    assert.equal(queue.pending.length,2); assert.equal(queue.sentCount,0);
    fail = false; await queue.flush();
    assert.deepEqual(received,[0,1]); assert.equal(queue.pending.length,0); assert.equal(queue.savedBytes,11);
});

test('serializes simultaneous data events without duplicate uploads', async () => {
    const received=[]; let unblock; const blocked = new Promise(resolve => unblock=resolve);
    const queue = new AudioUploadQueue(async chunk => { await blocked; received.push(chunk.sequence); });
    const first=queue.append(new Blob(['one'])); const second=queue.append(new Blob(['two'])); unblock();
    await Promise.all([first,second]); assert.deepEqual(received,[0,1]); assert.equal(queue.sentCount,2);
});
