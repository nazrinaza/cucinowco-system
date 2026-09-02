document.addEventListener('DOMContentLoaded', () => {
    const menuButton = document.querySelector('[data-menu-button]');
    const menu = document.querySelector('[data-menu]');
    menuButton?.addEventListener('click', () => {
        const open = menu?.classList.toggle('open') ?? false;
        menuButton.setAttribute('aria-expanded', String(open));
    });
    menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
        menu.classList.remove('open');
        menuButton?.setAttribute('aria-expanded', 'false');
    }));
    document.querySelector('[data-admin-menu]')?.addEventListener('click', () => {
        document.querySelector('[data-admin-sidebar]')?.classList.toggle('open');
    });

    document.querySelectorAll('[data-accordion]').forEach((accordion) => {
        const items = [...accordion.querySelectorAll('details')];

        items.forEach((item) => item.addEventListener('toggle', () => {
            if (!item.open) return;

            items.forEach((otherItem) => {
                if (otherItem !== item) otherItem.open = false;
            });
        }));
    });
});
