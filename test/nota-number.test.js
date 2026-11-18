const test = require('node:test');
const assert = require('node:assert/strict');
const { generateNotaNumber } = require('../lib/nota-number');

test('creates a compact service nota using YYMMDD and a three-character base36 suffix', () => {
    const nota = generateNotaNumber(new Date('2026-10-30T12:00:00.000Z'), 35);
    assert.equal(nota, 'SRV-261030-00Z');
    assert.match(nota, /^SRV-\d{6}-[0-9A-Z]{3}$/);
    assert.equal(nota.length, 14);
});

test('rejects suffix values outside the three-character base36 range', () => {
    assert.throws(() => generateNotaNumber(new Date('2026-10-30T12:00:00.000Z'), 36 ** 3));
});
