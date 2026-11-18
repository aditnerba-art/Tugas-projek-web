const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const ownerPage = fs.readFileSync(path.join(__dirname, '../public/owner.html'), 'utf8');

test('owner panel links to the spareparts management page', () => {
    assert.match(ownerPage, /<a\b[^>]*href="\/spareparts\.html"[^>]*>[^<]*📦[^<]*Sparepart/i);
});
