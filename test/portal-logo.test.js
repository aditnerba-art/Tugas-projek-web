const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const portalPath = path.join(__dirname, '../public/index.html');
const html = fs.readFileSync(portalPath, 'utf8');

test('customer portal header uses the supplied TECKFO logo asset', () => {
    assert.match(html, /<img[^>]*class="portal-brand-logo"[^>]*src="\/teckfo-logo\.png"[^>]*alt="TECKFO Computer logo"/);
    assert.equal(fs.existsSync(path.join(__dirname, '../public/teckfo-logo.png')), true);
});
