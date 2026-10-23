(() => {
    const key = 'larasvn.appearance';
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    const valid = value => ['light', 'dark', 'system'].includes(value) ? value : 'system';
    let preference = 'system';
    try { preference = valid(localStorage.getItem(key)); } catch { /* Browser storage is optional. */ }
    const apply = () => {
        document.documentElement.dataset.appearance = preference === 'system' ? (system.matches ? 'dark' : 'light') : preference;
        const control = document.querySelector('#appearance');
        if (control) control.value = preference;
    };
    apply();
    system.addEventListener('change', apply);
    window.addEventListener('storage', event => {
        if (event.key === key || event.key === null) {
            preference = valid(event.newValue);
            apply();
        }
    });
    document.addEventListener('DOMContentLoaded', () => {
        const control = document.querySelector('#appearance');
        control.closest('label').hidden = false;
        control.value = preference;
        control.addEventListener('change', () => {
            preference = valid(control.value);
            apply();
            try { localStorage.setItem(key, preference); } catch { /* Keep page-local preference. */ }
        });
    });
})();
