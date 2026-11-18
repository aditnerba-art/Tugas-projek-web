(() => {
    const rows = document.getElementById('staffAccountRows');
    const message = document.getElementById('staffAccountMessage');
    
    // Modal Edit Staf
    const editModal = document.getElementById('staffAccountModal');
    const editForm = document.getElementById('staffAccountForm');

    // Modal Tambah Staf
    const addModal = document.getElementById('addStaffModal');
    const addForm = document.getElementById('addStaffForm');

    const accounts = new Map();
    const roleLabels = { admin: 'Admin', teknisi: 'Teknisi' };

    function closeEditModal() {
        if (!editModal) return;
        editModal.classList.add('hidden');
        editModal.classList.remove('flex');
        if (editForm) editForm.reset();
        const editMessage = document.getElementById('staffEditMessage');
        if (editMessage) editMessage.textContent = '';
    }

    function closeAddModal() {
        if (!addModal) return;
        addModal.classList.add('hidden');
        addModal.classList.remove('flex');
        if (addForm) addForm.reset();
        const addMessage = document.getElementById('addStaffMessage');
        if (addMessage) addMessage.textContent = '';
    }

    function addCell(row, content, className = 'p-3') {
        const cell = document.createElement('td');
        cell.className = className;
        if (typeof content === 'string') {
            cell.textContent = content;
        } else if (content instanceof Node) {
            cell.appendChild(content);
        }
        row.appendChild(cell);
        return cell;
    }

    function renderAccounts(users) {
        rows.replaceChildren();
        accounts.clear();
        if (!users.length) {
            const row = document.createElement('tr');
            addCell(row, 'Belum ada akun admin atau teknisi terdaftar.', 'p-4 text-slate-500 text-center');
            rows.appendChild(row);
            return;
        }
        for (const user of users) {
            accounts.set(String(user.id), user);
            const row = document.createElement('tr');
            row.className = 'hover:bg-slate-50/80 transition';
            
            // Nama
            addCell(row, user.nama, 'p-3 font-semibold text-slate-800');
            
            // Role / Peran
            const roleBadge = document.createElement('span');
            roleBadge.className = user.role === 'admin'
                ? 'px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200'
                : 'px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200';
            roleBadge.textContent = roleLabels[user.role] || user.role;
            addCell(row, roleBadge, 'p-3');

            // Username
            addCell(row, `@${user.username}`, 'p-3 font-mono text-slate-700 text-xs');
            
            // Kolom Aksi: Edit & Hapus
            const actionCell = document.createElement('td');
            actionCell.className = 'p-3 text-right whitespace-nowrap space-x-1.5';

            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.dataset.editStaff = String(user.id);
            editBtn.className = 'px-2.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold transition shadow-sm inline-flex items-center gap-1';
            editBtn.innerHTML = '✏️ Edit';

            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.dataset.deleteStaff = String(user.id);
            deleteBtn.className = 'px-2.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold transition shadow-sm inline-flex items-center gap-1';
            deleteBtn.innerHTML = '🗑️ Hapus';

            actionCell.appendChild(editBtn);
            actionCell.appendChild(deleteBtn);
            row.appendChild(actionCell);

            rows.appendChild(row);
        }
    }

    async function loadAccounts() {
        rows.innerHTML = '<tr><td colspan="4" class="p-4 text-slate-500 text-center">Memuat akun staf…</td></tr>';
        message.textContent = '';
        message.className = 'text-sm text-slate-600';
        try {
            const response = await fetch('/api/owner/staff', { credentials: 'same-origin' });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Daftar akun tidak dapat dimuat.');
            renderAccounts(result.users || []);
        } catch (error) {
            rows.replaceChildren();
            message.className = 'text-sm text-rose-600 font-medium';
            message.textContent = error.message || 'Gagal memuat daftar akun staf.';
        }
    }

    // Refresh Tombol
    const reloadBtn = document.getElementById('reloadStaffAccounts');
    if (reloadBtn) reloadBtn.addEventListener('click', loadAccounts);

    // Buka Modal Tambah Staf Baru
    const openAddBtn = document.getElementById('openAddStaffModalBtn');
    if (openAddBtn) {
        openAddBtn.addEventListener('click', () => {
            if (!addModal) return;
            addForm.reset();
            const addMessage = document.getElementById('addStaffMessage');
            if (addMessage) addMessage.textContent = '';
            addModal.classList.remove('hidden');
            addModal.classList.add('flex');
            const namaInput = document.getElementById('addStaffNama');
            if (namaInput) namaInput.focus();
        });
    }

    // Tutup Modal Tambah Staf
    const cancelAddBtn = document.getElementById('cancelAddStaffBtn');
    if (cancelAddBtn) cancelAddBtn.addEventListener('click', closeAddModal);

    const closeAddTopBtn = document.getElementById('closeAddStaffModalTopBtn');
    if (closeAddTopBtn) closeAddTopBtn.addEventListener('click', closeAddModal);

    if (addModal) {
        addModal.addEventListener('click', event => { if (event.target === addModal) closeAddModal(); });
    }

    // Submit Form Tambah Staf Baru
    if (addForm) {
        addForm.addEventListener('submit', async event => {
            event.preventDefault();
            const nama = (document.getElementById('addStaffNama')?.value || '').trim();
            const username = (document.getElementById('addStaffUsername')?.value || '').trim();
            const role = (document.getElementById('addStaffRole')?.value || '').trim().toLowerCase();
            const password = document.getElementById('addStaffPassword')?.value || '';

            const addMessage = document.getElementById('addStaffMessage');
            if (addMessage) addMessage.textContent = '';

            if (!nama || nama.length < 2) {
                if (addMessage) addMessage.textContent = 'Nama lengkap staf wajib diisi (minimal 2 karakter).';
                return;
            }

            if (!username || username.length < 3) {
                if (addMessage) addMessage.textContent = 'Username wajib diisi (minimal 3 karakter).';
                return;
            }

            if (!['admin', 'teknisi'].includes(role)) {
                if (addMessage) addMessage.textContent = 'Pilih role antara Admin atau Teknisi.';
                return;
            }

            if (!password || password.length < 8) {
                if (addMessage) addMessage.textContent = 'Password wajib diisi (minimal 8 karakter).';
                return;
            }

            const submitBtn = document.getElementById('saveAddStaffBtn') || addForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Menyimpan…';

            try {
                const response = await fetch('/api/owner/staff', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nama, username, role, password })
                });

                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Gagal menambahkan akun staf.');

                closeAddModal();
                await loadAccounts();
                message.className = 'text-sm text-emerald-600 font-semibold';
                message.textContent = `✅ Akun ${roleLabels[role] || role} "${nama}" (@${username}) berhasil ditambahkan.`;
            } catch (err) {
                if (addMessage) addMessage.textContent = err.message || 'Gagal menambahkan akun staf.';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    // Event Delegation pada Tabel: Edit & Hapus
    rows.addEventListener('click', async event => {
        // 1. Klik Tombol Edit
        const editButton = event.target.closest('[data-edit-staff]');
        if (editButton) {
            const user = accounts.get(editButton.dataset.editStaff);
            if (!user) return;

            document.getElementById('staffAccountId').value = user.id;
            const identityEl = document.getElementById('staffAccountIdentity');
            if (identityEl) {
                identityEl.textContent = `Peran: ${roleLabels[user.role] || user.role} (ID: ${user.id})`;
            }

            const inputNama = document.getElementById('staffEditNama') || document.getElementById('staffNewName');
            if (inputNama) inputNama.value = user.nama || '';

            const inputUsername = document.getElementById('staffEditUsername') || document.getElementById('staffNewUsername');
            if (inputUsername) inputUsername.value = user.username || '';

            const inputPassword = document.getElementById('staffEditPassword') || document.getElementById('staffNewPassword');
            if (inputPassword) inputPassword.value = '';

            const editMessage = document.getElementById('staffEditMessage');
            if (editMessage) editMessage.textContent = '';

            editModal.classList.remove('hidden');
            editModal.classList.add('flex');
            if (inputNama) inputNama.focus();
            return;
        }

        // 2. Klik Tombol Hapus
        const deleteButton = event.target.closest('[data-delete-staff]');
        if (deleteButton) {
            const user = accounts.get(deleteButton.dataset.deleteStaff);
            if (!user) return;

            const confirmText = `Apakah Anda yakin ingin menghapus akun ${roleLabels[user.role] || user.role} "${user.nama}" (@${user.username})?\n\nPerhatian: Sesi login aktif staf akan dihapus dan penugasan teknisi pada servis terkait akan diatur ulang.`;
            if (!confirm(confirmText)) {
                return;
            }

            deleteButton.disabled = true;
            deleteButton.textContent = 'Menghapus…';

            try {
                const response = await fetch(`/api/owner/staff/${encodeURIComponent(user.id)}`, {
                    method: 'DELETE',
                    credentials: 'same-origin'
                });

                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Gagal menghapus akun staf.');

                await loadAccounts();
                message.className = 'text-sm text-emerald-600 font-semibold';
                message.textContent = `✅ Akun staf "${user.nama}" berhasil dihapus.`;
            } catch (err) {
                console.error('Delete staff error:', err);
                alert(`❌ ${err.message || 'Gagal menghapus akun staf'}`);
                deleteButton.disabled = false;
                deleteButton.innerHTML = '🗑️ Hapus';
            }
        }
    });

    // Tutup Modal Edit Staf
    const cancelEditBtn = document.getElementById('cancelStaffEdit');
    if (cancelEditBtn) cancelEditBtn.addEventListener('click', closeEditModal);

    const closeEditTopBtn = document.getElementById('closeStaffModalTopBtn');
    if (closeEditTopBtn) closeEditTopBtn.addEventListener('click', closeEditModal);

    if (editModal) {
        editModal.addEventListener('click', event => { if (event.target === editModal) closeEditModal(); });
    }

    // Escape Key Handler untuk kedua modal
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            if (editModal && !editModal.classList.contains('hidden')) closeEditModal();
            if (addModal && !addModal.classList.contains('hidden')) closeAddModal();
        }
    });

    // Submit Form Edit Staf
    if (editForm) {
        editForm.addEventListener('submit', async event => {
            event.preventDefault();
            const userId = document.getElementById('staffAccountId').value;

            const inputNama = document.getElementById('staffEditNama') || document.getElementById('staffNewName');
            const inputUsername = document.getElementById('staffEditUsername') || document.getElementById('staffNewUsername');
            const inputPassword = document.getElementById('staffEditPassword') || document.getElementById('staffNewPassword');

            const nama = (inputNama?.value || '').trim();
            const username = (inputUsername?.value || '').trim();
            const password = inputPassword?.value || '';

            const editMessage = document.getElementById('staffEditMessage');
            if (editMessage) editMessage.textContent = '';

            if (!nama || nama.length < 2) {
                if (editMessage) editMessage.textContent = 'Nama staf wajib diisi (minimal 2 karakter).';
                return;
            }

            if (!username || username.length < 3) {
                if (editMessage) editMessage.textContent = 'Username wajib diisi (minimal 3 karakter).';
                return;
            }

            if (password && password.length < 8) {
                if (editMessage) editMessage.textContent = 'Kata sandi baru minimal 8 karakter.';
                return;
            }

            const submit = document.getElementById('saveStaffBtn') || editForm.querySelector('button[type="submit"]');
            submit.disabled = true;
            const originalText = submit.textContent;
            submit.textContent = 'Menyimpan…';

            try {
                const payload = { nama, username };
                if (password) {
                    payload.password = password;
                }

                const response = await fetch(`/api/owner/staff/${encodeURIComponent(userId)}`, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Akun staf gagal diperbarui.');

                closeEditModal();
                await loadAccounts();
                message.className = 'text-sm text-emerald-600 font-semibold';
                message.textContent = `✅ Akun staf "${result.user?.nama || username}" berhasil diperbarui.${password ? ' Sesi login lama di-reset, staf harus login ulang.' : ''}`;
            } catch (error) {
                if (editMessage) editMessage.textContent = error.message || 'Akun staf gagal diperbarui.';
            } finally {
                submit.disabled = false;
                submit.textContent = originalText;
            }
        });
    }

    loadAccounts();
})();
