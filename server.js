const express = require('express');
const path = require('path');
const cors = require('cors');
const migrate = require('./config/migrate');

const db = require('./config/db');
const { createAuth } = require('./auth');

const app = express();
app.use(cors({
    origin: true,          // mirror the request origin (same-origin & localhost)
    credentials: true      // allow cookies to be forwarded with cross-origin requests
}));
app.use(express.json());

const auth = createAuth(db);
app.use('/api/auth', auth.router);

const fs = require('fs');
const servePage = (filename) => (req, res) => res.sendFile(path.join(__dirname, 'public', filename));

const hasLoginFile = (filename) => fs.existsSync(path.join(__dirname, 'public', filename));
const adminLoginRedirect = hasLoginFile('admin-login.html') ? '/admin-login.html' : '/login.html';
const teknisiLoginRedirect = hasLoginFile('teknisi-login.html') ? '/teknisi-login.html' : '/login.html';
const ownerLoginRedirect = hasLoginFile('owner-login.html') ? '/owner-login.html' : '/login.html';

app.get('/admin.html', ...auth.requirePageRoles(['admin'], adminLoginRedirect), servePage('admin.html'));
app.get('/teknisi.html', ...auth.requirePageRoles(['teknisi'], teknisiLoginRedirect), servePage('teknisi.html'));
app.get('/owner.html', ...auth.requirePageRoles(['owner'], ownerLoginRedirect), servePage('owner.html'));
app.get('/spareparts.html', ...auth.requirePageRoles(['admin', 'owner'], '/login.html'), servePage('spareparts.html'));

const { createCustomerRouter } = require('./routes/customer');
const customerRouter = createCustomerRouter(db);
app.use('/api/customer', customerRouter);
app.use('/api/tracking', customerRouter);

const { createAdminCustomersRouter } = require('./routes/admin-customers');
app.use('/api/admin/customers', ...auth.requireRoles(['admin']), createAdminCustomersRouter(db));

const adminRoutes = require('./routes/admin');
app.use('/api/admin', ...auth.requireRoles(['admin', 'teknisi']), (req, res, next) => {
    if (req.authUser.role === 'admin') return next();
    const technicianAllowed =
        (req.method === 'GET' && (req.path === '/services-active' || /^\/nota\/[^/]+$/.test(req.path))) ||
        (req.method === 'PUT' && req.path === '/service/status');
    if (technicianAllowed) return next();
    return res.status(403).json({ status: 'error', message: 'Akses admin diperlukan' });
}, adminRoutes);

const { createOwnerStaffRouter } = require('./routes/owner-staff');
app.use('/api/owner/staff', ...auth.requireRoles(['owner']), createOwnerStaffRouter(db));

const ownerRoutes = require('./routes/owner');
app.use('/api/owner', ...auth.requireRoles(['owner']), ownerRoutes);

const sparepartRoutes = require('./routes/spareparts');
app.use('/api/spareparts', ...auth.requireRoles(['admin', 'teknisi', 'owner']), (req, res, next) => {
    if (req.method === 'GET' || ['admin', 'owner'].includes(req.authUser.role)) return next();
    return res.status(403).json({ status: 'error', message: 'Akses admin atau owner diperlukan' });
}, sparepartRoutes);

// Login pages, shared browser scripts, and customer tracking remain static/public.
app.use(express.static(path.join(__dirname, 'public')));

const PORT = process.env.PORT || 3000;

// Jalankan auto migration lalu start server
migrate()
    .then(() => {
        app.listen(PORT, () => {
            console.log(`Server running di http://localhost:${PORT}`);
        });
    })
    .catch((err) => {
        console.error('Gagal inisialisasi database:', err);
        app.listen(PORT, () => {
            console.log(`Server running di http://localhost:${PORT} (Database warning)`);
        });
    });