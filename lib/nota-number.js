const { randomInt } = require('node:crypto');

const SUFFIX_SPACE = 36 ** 3;

function generateNotaNumber(date = new Date(), suffix = randomInt(SUFFIX_SPACE)) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
        throw new TypeError('Tanggal nota tidak valid');
    }
    if (!Number.isInteger(suffix) || suffix < 0 || suffix >= SUFFIX_SPACE) {
        throw new RangeError('Kode acak nota harus berada dalam rentang base36 tiga karakter');
    }

    const datePart = date.toISOString().slice(2, 10).replace(/-/g, '');
    const code = suffix.toString(36).toUpperCase().padStart(3, '0');
    return `SRV-${datePart}-${code}`;
}

module.exports = { generateNotaNumber };
