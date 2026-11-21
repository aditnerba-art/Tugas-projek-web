import QRCode from 'qrcode';

const target = document.getElementById('trackingQr');

if (target) {
    QRCode.toCanvas(target.dataset.url, {
        width: 112,
        margin: 1,
        errorCorrectionLevel: 'M',
    }).catch(error => {
        console.error('QR code tanda terima gagal dibuat', error);
        target.textContent = 'QR gagal dibuat. Gunakan tautan pelacakan di atas.';
    });
}
