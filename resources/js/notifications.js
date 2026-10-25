// Checks for new notifications every 30 seconds while the page is visible and the user has been active recently.
// Polling stops after 10 idle minutes so it does not keep an unattended session signed in.
const region = document.querySelector('[data-notification-poll]');

if (region) {
    const pollUrl = region.dataset.notificationPoll;
    const INTERVAL = 30000;
    const IDLE_LIMIT = 10 * 60 * 1000;
    let lastActivity = Date.now();
    let stopped = false;
    let seen = new Set();
    // Only the first poll of a browser tab sets the baseline silently; later page loads still toast new items,
    // even when the user had no notifications at all.
    let baseline = true;
    try {
        seen = new Set(JSON.parse(sessionStorage.getItem('rhu-seen-notifications') || '[]'));
        baseline = sessionStorage.getItem('rhu-notifications-baselined') !== '1';
    } catch {
        // Storage can be unavailable (private mode); toasts then rely on this page only.
    }

    ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach((type) => window.addEventListener(type, () => { lastActivity = Date.now(); }, { passive: true }));

    function remember(ids) {
        ids.forEach((id) => seen.add(id));
        try {
            sessionStorage.setItem('rhu-seen-notifications', JSON.stringify([...seen].slice(-100)));
            sessionStorage.setItem('rhu-notifications-baselined', '1');
        } catch {
            // Ignore storage failures.
        }
    }

    function updateCount(unread) {
        document.querySelectorAll('[data-unread-badge]').forEach((badge) => {
            badge.textContent = unread > 9 ? '9+' : String(unread);
            badge.hidden = unread === 0;
        });
        document.querySelectorAll('[data-unread-count]').forEach((count) => { count.textContent = String(unread); });
        document.querySelectorAll('[data-unread-mobile]').forEach((link) => { link.hidden = unread === 0; });
        document.querySelectorAll('[data-unread-link]').forEach((link) => link.setAttribute('aria-label', unread ? `Notifications, ${unread} unread` : 'Notifications'));
    }

    function toast(item) {
        const card = document.createElement(item.url ? 'a' : 'div');
        if (item.url) card.href = item.url;
        card.className = `pointer-events-auto block rounded-xl border p-4 text-sm shadow-lg ${item.urgent ? 'border-red-300 bg-red-50 text-red-900' : 'border-emerald-300 bg-white text-slate-900'}`;
        card.setAttribute('role', item.urgent ? 'alert' : 'status');
        const label = document.createElement('strong');
        label.className = 'block text-xs uppercase tracking-wide';
        label.textContent = item.urgent ? 'Urgent' : 'New notification';
        const text = document.createElement('span');
        text.className = 'mt-1 block';
        text.textContent = item.headline;
        card.append(label, text);
        region.append(card);
        setTimeout(() => card.remove(), item.urgent ? 20000 : 8000);
    }

    async function poll() {
        if (stopped || document.hidden || Date.now() - lastActivity > IDLE_LIMIT) return;
        try {
            const response = await fetch(pollUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!response.ok || !(response.headers.get('Content-Type') || '').includes('application/json')) {
                // Signed out, password change required or rate limited: stop instead of retrying blindly.
                if (response.status !== 429) stopped = true;
                return;
            }
            const data = await response.json();
            updateCount(data.unread);
            const fresh = data.latest.filter((item) => !seen.has(item.id));
            if (!baseline) fresh.reverse().forEach(toast);
            baseline = false;
            remember(data.latest.map((item) => item.id));
        } catch {
            // Network hiccup; try again on the next interval.
        }
    }

    poll();
    setInterval(poll, INTERVAL);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
}
