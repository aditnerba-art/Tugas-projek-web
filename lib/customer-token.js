const crypto = require('node:crypto');

function generateCustomerToken() {
    return crypto.randomInt(0, 100000).toString().padStart(5, '0');
}

function hashCustomerToken(token) {
    return crypto.createHash('sha256').update(String(token)).digest('hex');
}

function customerPortalUrl(value) {
    if (typeof value !== 'string' || !value.trim()) return null;
    try {
        const url = new URL(value.trim());
        const hostname = url.hostname.toLowerCase();
        if (url.protocol !== 'https:' || url.username || url.password ||
            !hostname.includes('.') || hostname === 'localhost' || hostname.endsWith('.local')) return null;
        const path = url.pathname.replace(/\/+$/, '');
        return `${url.origin}${path}/index.html`;
    } catch {
        return null;
    }
}

module.exports = { generateCustomerToken, hashCustomerToken, customerPortalUrl };
