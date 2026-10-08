import {describe, it} from 'node:test';
import assert from 'node:assert/strict';
import {arrayBufferToBase64, base64ToArrayBuffer} from '$lib/kernel/encryption/utils.js';

/** A deterministic non-trivial byte pattern so round-trip integrity is actually checked. */
function patternBuffer(length: number): ArrayBuffer {
    const bytes = new Uint8Array(length);
    for (let i = 0; i < length; i++) {
        bytes[i] = (i * 7 + (i >> 8)) & 0xff;
    }
    return bytes.buffer;
}

describe('arrayBufferToBase64', () => {
    it('encodes an empty buffer', () => {
        assert.equal(arrayBufferToBase64(new ArrayBuffer(0)), '');
    });

    it('encodes a small buffer', () => {
        const bytes = new Uint8Array([0x48, 0x65, 0x6c, 0x6c, 0x6f, 0x21]);
        assert.equal(arrayBufferToBase64(bytes.buffer), btoa('Hello!'));
    });

    it('handles buffers far larger than the engine argument limit', () => {
        // Regression: this used to go through String.fromCharCode.apply() with
        // every byte as an argument, throwing a RangeError on ~2MB payloads
        // (client-side encryption of messages with embedded base64 images).
        const buffer = patternBuffer(1_000_003); // deliberately not chunk-aligned
        const base64 = arrayBufferToBase64(buffer);

        assert.ok(base64.length > 1_000_000);

        const restored = new Uint8Array(base64ToArrayBuffer(base64));
        assert.equal(restored.length, 1_000_003);
        const original = new Uint8Array(buffer);
        for (let i = 0; i < restored.length; i++) {
            assert.equal(restored[i], original[i], `byte mismatch at ${i}`);
        }
    });
});

describe('base64ToArrayBuffer', () => {
    it('round-trips a chunk-boundary-sized buffer', () => {
        const buffer = patternBuffer(0x8000);
        const restored = new Uint8Array(base64ToArrayBuffer(arrayBufferToBase64(buffer)));
        const original = new Uint8Array(buffer);
        assert.deepEqual([...restored], [...original]);
    });
});
