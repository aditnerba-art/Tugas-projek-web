const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const publicDir = path.join(__dirname, '../public');
const theme = fs.readFileSync(path.join(publicDir, 'theme.css'), 'utf8');
const auth = fs.readFileSync(path.join(publicDir, 'auth.css'), 'utf8');

for (const page of ['admin', 'teknisi', 'owner', 'spareparts']) {
    test(`${page} page uses the shared premium dashboard theme`, () => {
        const html = fs.readFileSync(path.join(publicDir, `${page}.html`), 'utf8');
        assert.match(html, /href="\/theme\.css"/);
        assert.match(html, /<body[^>]*data-theme="dashboard"/);
    });
}

test('shared theme defines midnight, electric-blue, cyan, responsive and reduced-motion rules', () => {
    assert.match(theme, /--ui-midnight:\s*#061226/i);
    assert.match(theme, /--ui-electric:\s*#347cff/i);
    assert.match(theme, /--ui-cyan:\s*#53e5f5/i);
    assert.match(theme, /@media\s*\(max-width:\s*760px\)/);
    assert.match(theme, /@media\s*\(prefers-reduced-motion:\s*reduce\)/);
});

test('staff login pages share the same midnight and cyan palette', () => {
    assert.match(auth, /#061226/i);
    assert.match(auth, /#53e5f5/i);
    assert.match(auth, /#347cff/i);
});

test('printable nota page and route are removed', () => {
    const server = fs.readFileSync(path.join(__dirname, '../server.js'), 'utf8');
    assert.equal(fs.existsSync(path.join(publicDir, 'nota.html')), false);
    assert.doesNotMatch(server, /app\.get\('\/nota\.html'/);
    assert.doesNotMatch(theme, /data-theme="receipt"|receipt-paper/);
});

test('admin builds WhatsApp links with normalized Indonesian phone numbers', () => {
    const admin = fs.readFileSync(path.join(publicDir, 'admin.html'), 'utf8');
    const helpers = admin.match(/function formatNomorWA\(noHp\) \{[\s\S]*?\n        \}\n\n        function buatLinkWA\(noHp, namaPelanggan, perangkat, kodeAkses\) \{[\s\S]*?\n        \}/);
    assert.ok(helpers);

    const context = {
        window: { location: { origin: 'https://service.example' } },
        encodeURIComponent
    };
    vm.runInNewContext(`${helpers[0]}\nglobalThis.waHelpers = { formatNomorWA, buatLinkWA };`, context);
    assert.equal(context.waHelpers.formatNomorWA('0812-345 678'), '62812345678');

    const link = new URL(context.waHelpers.buatLinkWA('0812 345-678', 'Sari', 'Laptop Asus', '12345'));
    assert.equal(link.hostname, 'wa.me');
    assert.equal(link.pathname, '/62812345678');
    const message = link.searchParams.get('text');
    assert.match(message, /Halo \*Sari\*/);
    assert.match(message, /Perangkat Anda \(\*Laptop Asus\*\)/);
    assert.match(message, /\*Kode Akses Pelanggan:\* \*12345\*/);
    assert.match(message, /\*Link Cek Status:\* https:\/\/service\.example\/index\.html/);
    assert.match(message, /1\. Memantau progres pengerjaan servis secara real-time\./);
    assert.match(message, /2\. Melihat hasil pengecekan teknisi & rincian estimasi biaya\./);
    assert.match(message, /3\. Melakukan \*konfirmasi setuju \(lanjut servis\) atau batal\*/);
    assert.match(message, /Simpan kode akses Anda untuk pengecekan berkala\. Terima kasih!/);
    assert.match(admin, /id="successWhatsappLink" target="_blank"/);
    assert.match(admin, /sendWhatsappLink\.target = '_blank'/);
    assert.match(admin, /navigator\.clipboard\.writeText\(code\)/);
});

test('public tracking labels use kode akses without changing backend field names', () => {
    const admin = fs.readFileSync(path.join(publicDir, 'admin.html'), 'utf8');
    const portal = fs.readFileSync(path.join(publicDir, 'index.html'), 'utf8');
    assert.doesNotMatch(admin, /Nomor Nota|No\. Nota/);
    assert.doesNotMatch(portal, /Nomor Nota|No\. Nota/);
    assert.match(admin, /<th class="p-3\.5">Kode Akses<\/th>/);
    assert.match(admin, /<th class="p-3\.5">Kode Akses Pelanggan<\/th>/);
    assert.match(admin, /srv\.no_nota/);
    assert.match(portal, /unit\.no_nota/);
});

test('admin keeps access-code loading and rotation in the service table without the removed panel', () => {
    const admin = fs.readFileSync(path.join(publicDir, 'admin.html'), 'utf8');
    assert.doesNotMatch(admin, /id="accessCodesTitle"|id="accessCodesTable"/);
    assert.match(admin, /async function loadAccessCodes\(\)/);
    assert.match(admin, /async function rotateCustomerAccessCode\(serviceId, button\)/);
    assert.match(admin, /api\/admin\/customers\/access-codes\/\$\{encodeURIComponent\(serviceId\)\}/);
    assert.match(admin, /createCodeButton\.textContent = hasActiveCode \? 'Buat Ulang' : 'Buat Kode'/);
});
