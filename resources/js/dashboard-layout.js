function fitDashboard() {
    const panels = document.querySelector('[data-dashboard-panels]');
    if (!panels) return;
    const main = panels.closest('main');
    let frame;
    function size() {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            const height = window.visualViewport?.height || window.innerHeight;
            const top = panels.getBoundingClientRect().top + window.scrollY;
            const bottom = parseFloat(getComputedStyle(main).paddingBottom) || 0;
            const available = Math.floor(height - top - bottom - 2);
            // Keep content reachable on small/zoomed screens rather than clipping it.
            const fits = window.innerWidth >= 1280 && available >= 220;
            panels.classList.toggle('dashboard-panels-fitted', fits);
            panels.style.setProperty('--dashboard-panel-height', `${available}px`);
        });
    }
    const observer = new ResizeObserver(size);
    observer.observe(main.querySelector('[aria-labelledby="dashboard-summary-title"]'));
    window.addEventListener('resize', size);
    window.visualViewport?.addEventListener('resize', size);
    document.fonts?.ready.then(size);
    size();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fitDashboard, { once: true });
else fitDashboard();
