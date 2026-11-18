const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const html = fs.readFileSync(path.join(__dirname, '../public/index.html'), 'utf8');
const styles = html.match(/<style>([\s\S]*?)<\/style>/i)?.[1] || '';

test('customer login benefits and every dashboard feature box use the blue card system', () => {
    const dashboardCards = html.match(/class="[^"]*portal-feature-card[^"]*"/g) || [];
    assert.equal(dashboardCards.length, 5);
    assert.match(styles, /#resultPage \.portal-feature-card\s*\{[^}]*background:\s*linear-gradient\(145deg,\s*#ffffff,\s*#f4f8ff\)/);
    assert.match(styles, /#resultPage \.portal-feature-card::before\s*\{[^}]*radial-gradient/);
    assert.match(styles, /#resultPage \.portal-feature-card::after\s*\{/);
    assert.match(styles, /\.portal-feature-card:hover\s*\{/);
    assert.match(styles, /\.login-benefit\s*\{[^}]*background:[^;]*linear-gradient\(135deg,\s*#ffffff/);
    assert.match(styles, /\.portal-card-icon\s*\{/);
    assert.match(styles, /\.timeline-entry\s*\{/);
    assert.match(html, /class="[^"]*portal-info-grid[^"]*"/);
    assert.match(html, /class="[^"]*portal-payment-panel[^"]*"/);
});
