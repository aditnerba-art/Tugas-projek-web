const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const publicDir = path.join(__dirname, '../public');
const html = fs.readFileSync(path.join(publicDir, 'index.html'), 'utf8');
const styles = html.match(/<style>([\s\S]*?)<\/style>/i)?.[1] || '';

test('customer portal has a silent looping background video', () => {
    assert.match(html, /<video[^>]*class="portal-background-video"[^>]*autoplay[^>]*loop[^>]*muted[^>]*playsinline/i);
    assert.match(html, /src="\/portal-background\.mp4"/);
    assert.match(html, /poster="\/portal-background-poster\.jpg"/);
    assert.equal(fs.existsSync(path.join(publicDir, 'portal-background.mp4')), true);
    assert.equal(fs.existsSync(path.join(publicDir, 'portal-background-poster.jpg')), true);
});

test('background video stays behind content and is disabled for reduced motion', () => {
    assert.match(styles, /\.portal-background-video\s*\{[^}]*position:\s*fixed/);
    assert.match(styles, /#loginPage, #resultPage\s*\{[^}]*z-index:\s*1/);
    assert.match(styles, /\.portal-nav\s*\{[^}]*z-index:\s*1/);
    assert.match(styles, /@media\s*\(prefers-reduced-motion:\s*reduce\)[\s\S]*?\.portal-background-video[^}]*display:\s*none/);
});
