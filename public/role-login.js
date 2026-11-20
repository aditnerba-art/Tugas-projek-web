(() => {
    const role = document.body.dataset.role;
    const labels = { admin: 'Admin', teknisi: 'Teknisi', owner: 'Owner' };
    const form = document.getElementById('loginForm');
    const message = document.getElementById('loginMessage');

    fetch('/api/auth/me').then(async response => {
        if (!response.ok) return;
        const { user } = await response.json();
        if (user?.must_change_password) return window.location.replace('/password/change');
        if (user?.role === role) window.location.replace(`/${role}.html`);
        else if (['admin', 'teknisi', 'owner'].includes(user?.role)) window.location.replace(`/${user.role}.html`);
    }).catch(() => {});

    form.addEventListener('submit', async event => {
        event.preventDefault();
        message.textContent = '';
        const submit = form.querySelector('button[type="submit"]');
        submit.disabled = true;
        submit.textContent = 'Memeriksa…';
        try {
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    username: document.getElementById('username').value,
                    password: document.getElementById('password').value,
                    role
                })
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Login gagal.');
            if (result.user?.role !== role) throw new Error('Akun ini tidak memiliki akses halaman tersebut.');
            if (result.user?.must_change_password) {
                window.location.assign('/password/change');
                return;
            }
            window.location.assign(`/${role}.html`);
        } catch (error) {
            message.textContent = error.message || `Tidak dapat login sebagai ${labels[role] || 'staf'}.`;
        } finally {
            submit.disabled = false;
            submit.textContent = `Masuk sebagai ${labels[role] || 'Staf'}`;
        }
    });
})();
