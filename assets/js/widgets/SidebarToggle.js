const STORAGE_KEY = 'sidebar-hidden';

export default class SidebarToggle {

    static init() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.sidebar-toggle-btn, .sidebar-show-btn');
            if (button === null) {
                return;
            }
            event.preventDefault();
            const isHidden = document.documentElement.classList.toggle('sidebar-hidden');
            window.localStorage.setItem(STORAGE_KEY, isHidden ? '1' : '0');
        });
    }

}
