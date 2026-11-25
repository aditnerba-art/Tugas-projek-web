(() => {
    const script = document.currentScript;
    const requiredRole = script?.dataset.role;
    const roleNames = { admin: 'Admin', teknisi: 'Teknisi', owner: 'Owner' };
    const panelPaths = { admin: '/admin', teknisi: '/teknisi', owner: '/owner' };

    const styles = document.createElement('style');
    styles.textContent = `
      .account-bar{display:flex;align-items:center;justify-content:flex-end;gap:9px;margin:0 0 16px;padding:9px 12px;border:1px solid #d6deea;border-radius:10px;background:#f8fafc;color:#243246;font:14px system-ui,sans-serif}
      .account-bar .account-name{margin-right:auto;color:#53647a}.account-bar button,.account-dialog button{border:0;border-radius:7px;padding:8px 11px;background:#1d4ed8;color:#fff;font:600 13px system-ui,sans-serif;cursor:pointer}.account-bar button.secondary{background:#e7edf5;color:#26364a}
      .account-overlay[hidden]{display:none}.account-overlay{position:fixed;inset:0;z-index:99999;display:grid;place-items:center;padding:18px;background:#0a1524a6}
      .account-dialog{width:min(100%,430px);max-height:90vh;overflow:auto;border:1px solid #ccd7e4;border-radius:14px;background:#fff;color:#182536;padding:22px;box-shadow:0 24px 70px #0005;font:14px/1.45 system-ui,sans-serif}
      .account-dialog h2{margin:0 0 5px;font-size:21px}.account-dialog p{margin:0 0 16px;color:#53647a}.account-dialog label{display:grid;gap:6px;margin:12px 0;color:#34465d;font-size:13px}.account-dialog input{width:100%;padding:10px;border:1px solid #bfcbda;border-radius:7px;font:14px system-ui,sans-serif}.account-dialog .actions{display:flex;gap:8px;justify-content:flex-end;margin-top:17px}.account-dialog button.secondary{background:#e7edf5;color:#26364a}.account-dialog .account-message{min-height:20px;color:#176b47;font-size:13px}
      @media(max-width:600px){.account-bar{flex-wrap:wrap}.account-bar .account-name{width:100%}}
    `;
    document.head.appendChild(styles);

    function loginPath(role) { return `/${role}/login`; }

    async function start() {
        let response;
        try {
            response = await fetch('/api/auth/me');
        } catch {
            return;
        }
        if (response.status === 401) return window.location.replace(loginPath(requiredRole));
        if (!response.ok) return;
        const result = await response.json();
        const user = result.user;
        if (!user || user.role !== requiredRole) {
            if (panelPaths[user?.role]) window.location.replace(panelPaths[user.role]);
            return;
        }

        const bar = document.createElement('div');
        bar.className = 'account-bar';
        const name = document.createElement('span');
        name.className = 'account-name';
        name.textContent = `Masuk sebagai ${user.username} · ${roleNames[user.role]}`;
        const settings = document.createElement('button');
        settings.type = 'button';
        settings.textContent = 'Akun & keamanan';
        const changePassword = document.createElement('button');
        changePassword.type = 'button';
        changePassword.textContent = 'Ubah kata sandi';
        changePassword.addEventListener('click', () => window.location.assign('/password/change'));
        const logout = document.createElement('button');
        logout.type = 'button';
        logout.className = 'secondary';
        logout.textContent = user.role === 'owner' ? 'Logout' : 'Keluar';
        if (user.role === 'owner') bar.append(name, settings, changePassword, logout);
        else bar.append(name, changePassword, logout);
        const ownerAccountSlot = requiredRole === 'owner' && document.getElementById('ownerAccountSlot');
        if (ownerAccountSlot) ownerAccountSlot.appendChild(bar);
        else document.body.insertBefore(bar, document.body.firstChild);

        const overlay = document.createElement('div');
        overlay.className = 'account-overlay';
        overlay.hidden = true;
        overlay.innerHTML = `<section class="account-dialog" role="dialog" aria-modal="true" aria-labelledby="accountTitle">
          <h2 id="accountTitle">Pengaturan akun</h2>
          <p>Ubah username atau kata sandi akun ${roleNames[user.role]}. Masukkan kata sandi saat ini untuk menyimpan.</p>
          <form id="accountForm" autocomplete="off">
            <label>Username saat ini<input id="accountCurrentUsername" readonly></label>
            <label>Username baru<input id="accountNewUsername" autocomplete="username" minlength="3" maxlength="32" placeholder="Kosongkan jika tidak diubah"></label>
            <label>Kata sandi saat ini<input id="accountCurrentPassword" type="password" autocomplete="current-password" required></label>
            <label>Kata sandi baru<input id="accountNewPassword" type="password" autocomplete="new-password" minlength="10" placeholder="Minimal 10 karakter"></label>
            <label>Ulangi kata sandi baru<input id="accountConfirmPassword" type="password" autocomplete="new-password" minlength="10"></label>
            <div class="account-message" id="accountMessage" role="status" aria-live="polite"></div>
            <div class="actions"><button type="button" class="secondary" id="accountCancel">Batal</button><button type="submit">Simpan perubahan</button></div>
          </form>
        </section>`;
        if (user.role === 'owner') document.body.appendChild(overlay);

        const form = overlay.querySelector('#accountForm');
        const currentUsername = overlay.querySelector('#accountCurrentUsername');
        const newUsername = overlay.querySelector('#accountNewUsername');
        const currentPassword = overlay.querySelector('#accountCurrentPassword');
        const newPassword = overlay.querySelector('#accountNewPassword');
        const confirmPassword = overlay.querySelector('#accountConfirmPassword');
        const message = overlay.querySelector('#accountMessage');
        currentUsername.value = user.username;

        settings.addEventListener('click', () => {
            message.textContent = '';
            overlay.hidden = false;
            currentPassword.focus();
        });
        overlay.querySelector('#accountCancel').addEventListener('click', () => { overlay.hidden = true; });
        overlay.addEventListener('click', event => { if (event.target === overlay) overlay.hidden = true; });
        document.addEventListener('keydown', event => { if (event.key === 'Escape') overlay.hidden = true; });

        form.addEventListener('submit', async event => {
            event.preventDefault();
            message.textContent = '';
            if (newPassword.value && newPassword.value !== confirmPassword.value) {
                message.textContent = 'Ulangan kata sandi baru tidak sama.';
                return;
            }
            if (!newUsername.value.trim() && !newPassword.value) {
                message.textContent = 'Masukkan username atau kata sandi baru.';
                return;
            }
            const submit = form.querySelector('button[type="submit"]');
            submit.disabled = true;
            try {
                const update = await fetch('/api/auth/account', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        currentPassword: currentPassword.value,
                        newUsername: newUsername.value.trim(),
                        newPassword: newPassword.value,
                        newPassword_confirmation: confirmPassword.value
                    })
                });
                const data = await update.json();
                if (!update.ok) throw new Error(data.message || 'Perubahan gagal disimpan.');
                user.username = data.user.username;
                currentUsername.value = user.username;
                name.textContent = `Masuk sebagai ${user.username} · ${roleNames[user.role]}`;
                form.reset();
                currentUsername.value = user.username;
                message.textContent = 'Perubahan tersimpan. Sesi login diperbarui.';
            } catch (error) {
                message.textContent = error.message || 'Tidak dapat menyimpan perubahan.';
            } finally {
                submit.disabled = false;
            }
        });

        logout.addEventListener('click', async () => {
            try { await fetch('/api/auth/logout', { method: 'POST' }); } catch {}
            window.location.replace(loginPath(requiredRole));
        });
    }

    start();
})();
