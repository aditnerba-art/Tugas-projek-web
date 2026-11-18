const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const html = fs.readFileSync(path.join(__dirname, '../public/index.html'), 'utf8');
const styles = html.match(/<style>([\s\S]*?)<\/style>/i)?.[1] || '';

test('customer portal login and result page have entrance motion', () => {
    assert.match(styles, /@keyframes\s+portal-rise/);
    assert.match(styles, /\.login-intro[\s\S]*?portal-rise/);
    assert.match(styles, /#resultPage:not\(\.hidden\)[\s\S]*?portal-rise/);
});

test('portal motion respects reduced-motion preference', () => {
    assert.match(styles, /@media\s*\(prefers-reduced-motion:\s*reduce\)[\s\S]*?animation-duration:\s*\.01ms\s*!important/);
});
