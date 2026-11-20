(() => {
    const root = document.createElement('section');
    root.id = 'panelNotifications';
    root.style.cssText = 'position:fixed;right:20px;top:104px;z-index:10000;font:14px/1.45 system-ui,sans-serif;color:#0b1e3f';

    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Notifikasi';
    button.style.cssText = 'position:relative;padding:10px 14px;border:1px solid #d6deea;border-radius:10px;background:#fff;color:#0b1e3f;font-weight:700;box-shadow:0 6px 20px #0b1e3f20;cursor:pointer';
    const badge = document.createElement('span');
    badge.style.cssText = 'display:none;position:absolute;top:-7px;right:-7px;min-width:19px;padding:2px 5px;border-radius:999px;background:#dc2626;color:#fff;font-size:11px';
    button.appendChild(badge);

    const panel = document.createElement('div');
    panel.hidden = true;
    panel.style.cssText = 'position:absolute;top:48px;right:0;width:min(360px,calc(100vw - 32px));max-height:70vh;overflow:auto;border:1px solid #d6deea;border-radius:12px;background:#fff;box-shadow:0 18px 48px #0b1e3f30';

    const header = document.createElement('div');
    header.style.cssText = 'position:sticky;top:0;display:flex;justify-content:space-between;align-items:center;padding:12px 14px;background:#0b1e3f;color:#fff';
    const heading = document.createElement('strong');
    heading.textContent = 'Notifikasi';
    const markAll = document.createElement('button');
    markAll.type = 'button';
    markAll.textContent = 'Tandai semua dibaca';
    markAll.style.cssText = 'border:0;background:transparent;color:#fbbf24;font-size:11px;font-weight:700;cursor:pointer';
    header.append(heading, markAll);

    const list = document.createElement('div');
    let previousUnreadCount = null;
    panel.append(header, list);
    root.append(button, panel);
    document.body.appendChild(root);

    function render(items) {
        list.replaceChildren();
        if (!items.length) {
            const empty = document.createElement('p');
            empty.textContent = 'Belum ada notifikasi.';
            empty.style.cssText = 'padding:14px;color:#64748b';
            list.appendChild(empty);
            return;
        }

        items.forEach(item => {
            const row = document.createElement('article');
            row.style.cssText = `padding:12px 14px;border-bottom:1px solid #e8edf4;${item.read_at ? '' : 'background:#eff6ff'}`;
            const title = document.createElement('strong');
            title.textContent = item.data?.title || 'Pemberitahuan';
            const message = document.createElement('p');
            message.textContent = item.data?.message || '';
            message.style.cssText = 'margin:5px 0;color:#52647e;font-size:12px';
            const time = document.createElement('time');
            time.dateTime = item.created_at || '';
            time.textContent = item.created_at ? new Date(item.created_at).toLocaleString('id-ID') : '';
            time.style.cssText = 'color:#94a3b8;font-size:10px';
            row.append(title, message, time);
            if (!item.read_at) {
                row.tabIndex = 0;
                row.style.cursor = 'pointer';
                row.addEventListener('click', () => markRead(item.id));
            }
            list.appendChild(row);
        });
    }

    async function refresh() {
        try {
            const response = await fetch('/api/notifications');
            if (!response.ok) return;
            const result = await response.json();
            const count = Number(result.unread_count || 0);
            if (previousUnreadCount !== null && count > previousUnreadCount) {
                window.dispatchEvent(new CustomEvent('panel-notification'));
            }
            previousUnreadCount = count;
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = count ? 'inline-block' : 'none';
            render(result.data || []);
        } catch (error) {
            console.error('Gagal memuat notifikasi panel', error);
        }
    }

    async function markRead(id) {
        try {
            const response = await fetch(`/api/notifications/${encodeURIComponent(id)}/read`, { method: 'POST' });
            if (!response.ok) throw new Error('Notifikasi gagal ditandai dibaca.');
            await refresh();
        } catch (error) {
            console.error(error);
        }
    }

    button.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        if (!panel.hidden) refresh();
    });
    markAll.addEventListener('click', async () => {
        try {
            const response = await fetch('/api/notifications/read-all', { method: 'POST' });
            if (!response.ok) throw new Error('Notifikasi gagal ditandai dibaca.');
            await refresh();
        } catch (error) {
            console.error(error);
        }
    });

    refresh();
    window.setInterval(refresh, 15000);
})();
