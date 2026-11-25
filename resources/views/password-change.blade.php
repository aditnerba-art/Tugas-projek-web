<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ubah Kata Sandi | TECKFO Service</title>
    <link rel="stylesheet" href="/auth.css">
</head>
<body>
<main class="card">
    <div class="mark">TECKFO Service</div>
    <h1>Ubah Kata Sandi</h1>
    <p>Untuk keamanan akun, buat kata sandi baru sebelum melanjutkan.</p>
    <form id="passwordForm" autocomplete="off">
        <div class="field">
            <label for="currentPassword">Kata sandi saat ini</label>
            <input id="currentPassword" name="current_password" type="password" autocomplete="current-password" required>
        </div>
        <div class="field">
            <label for="newPassword">Kata sandi baru</label>
            <input id="newPassword" name="password" type="password" autocomplete="new-password" minlength="10" required>
        </div>
        <div class="field">
            <label for="passwordConfirmation">Ulangi kata sandi baru</label>
            <input id="passwordConfirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required>
        </div>
        <button class="btn" type="submit">Simpan kata sandi</button>
        <div id="passwordMessage" class="message" role="alert" aria-live="polite"></div>
    </form>
</main>
<script>
    document.getElementById('passwordForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const message = document.getElementById('passwordMessage');
        const button = form.querySelector('button[type="submit"]');
        message.textContent = '';
        button.disabled = true;
        try {
            const response = await fetch('/api/auth/password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                current_password: document.getElementById('currentPassword').value,
                password: document.getElementById('newPassword').value,
                password_confirmation: document.getElementById('passwordConfirmation').value
                })
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'Kata sandi gagal diperbarui.');
            const panelPaths = { admin: '/admin', teknisi: '/teknisi', owner: '/owner' };
            window.location.replace(panelPaths[result.user.role] || '/login');
        } catch (error) {
            message.textContent = error.message || 'Tidak dapat menyimpan kata sandi.';
        } finally {
            button.disabled = false;
        }
    });
</script>
</body>
</html>
