import './bootstrap';

window.parseRupiahInput = value => {
    const digits = String(value ?? '').replace(/\D/g, '');
    return digits ? Number.parseInt(digits, 10) : 0;
};

window.formatRupiahInput = input => {
    const digits = String(input.value ?? '').replace(/\D/g, '');
    input.value = digits ? Number.parseInt(digits, 10).toLocaleString('id-ID') : '';
};

window.confirmRupiahInput = (input, label = 'Nilai') => {
    const amount = window.parseRupiahInput(input.value);
    const threshold = Number(input.dataset.confirmOver || 10000000);
    return amount <= threshold || window.confirm(
        `${label} Rp ${amount.toLocaleString('id-ID')} cukup besar. Pastikan jumlah nol sudah benar. Lanjutkan?`,
    );
};

function installRupiahInput(input) {
    if (input.dataset.rupiahInitialized) return;
    input.dataset.rupiahInitialized = 'true';
    input.inputMode = 'numeric';
    input.addEventListener('input', () => window.formatRupiahInput(input));
    window.formatRupiahInput(input);
}

function installRupiahInputs(root = document) {
    root.querySelectorAll('[data-rupiah-input]').forEach(installRupiahInput);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => installRupiahInputs(), { once: true });
} else {
    installRupiahInputs();
}

new MutationObserver(records => {
    records.forEach(record => record.addedNodes.forEach(node => {
        if (!(node instanceof Element)) return;
        if (node.matches('[data-rupiah-input]')) installRupiahInput(node);
        installRupiahInputs(node);
    }));
}).observe(document.documentElement, { childList: true, subtree: true });
