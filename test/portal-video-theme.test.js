const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const publicDir = path.join(__dirname, '../public');
const html = fs.readFileSync(path.join(publicDir, 'index.html'), 'utf8');
const styles = html.match(/<style>([\s\S]*?)<\/style>/i)?.[1] || '';

test('portal background uses a deep-blue brand palette with readable login copy', () => {
    assert.match(styles, /--portal-bg:\s*#071a35/i);
    assert.match(styles, /\.portal-background-video\s*\{[^}]*opacity:\s*\.9/);
    assert.match(styles, /\.login-intro h1\s*\{[^}]*color:\s*#f4f8ff/i);
    assert.match(styles, /\.login-intro > p\s*\{[^}]*color:\s*#c2d4ed/i);
    assert.equal(fs.existsSync(path.join(publicDir, 'portal-background-poster.jpg')), true);
});
