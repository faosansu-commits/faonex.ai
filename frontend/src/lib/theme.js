import { writable } from 'svelte/store';

const STORAGE_KEY = 'faonex-theme';

function createThemeStore() {
	const { subscribe, set: setStore } = writable('dark');

	function apply(value) {
		const next = value === 'light' ? 'light' : 'dark';
		if (typeof document !== 'undefined') {
			document.documentElement.dataset.theme = next;
		}
		if (typeof localStorage !== 'undefined') {
			localStorage.setItem(STORAGE_KEY, next);
		}
		setStore(next);
	}

	function init() {
		if (typeof localStorage === 'undefined') return;
		const saved = localStorage.getItem(STORAGE_KEY);
		apply(saved === 'light' ? 'light' : 'dark');
	}

	function toggle() {
		const current = typeof document !== 'undefined' && document.documentElement.dataset.theme === 'light' ? 'light' : 'dark';
		apply(current === 'light' ? 'dark' : 'light');
	}

	return { subscribe, init, toggle, set: apply };
}

export const theme = createThemeStore();
