/** @type {import('tailwindcss').Config} */
export default {
	content: ['./src/**/*.{html,js,svelte,ts}'],
	theme: {
		extend: {
			colors: {
				ink: {
					950: 'rgb(var(--c-ink-950) / <alpha-value>)',
					900: 'rgb(var(--c-ink-900) / <alpha-value>)',
					850: 'rgb(var(--c-ink-850) / <alpha-value>)',
					800: 'rgb(var(--c-ink-800) / <alpha-value>)',
					700: 'rgb(var(--c-ink-700) / <alpha-value>)',
					600: 'rgb(var(--c-ink-600) / <alpha-value>)'
				},
				slate: {
					100: 'rgb(var(--c-slate-100) / <alpha-value>)',
					200: 'rgb(var(--c-slate-200) / <alpha-value>)',
					300: 'rgb(var(--c-slate-300) / <alpha-value>)',
					400: 'rgb(var(--c-slate-400) / <alpha-value>)',
					500: 'rgb(var(--c-slate-500) / <alpha-value>)',
					600: 'rgb(var(--c-slate-600) / <alpha-value>)'
				}
			},
			fontFamily: {
				sans: ['Sarabun', 'ui-sans-serif', 'system-ui', 'sans-serif'],
				display: ['Orbitron', 'ui-sans-serif', 'sans-serif']
			},
			boxShadow: {
				glow: '0 0 20px rgba(168,85,247,0.45)',
				'glow-lg': '0 0 45px rgba(168,85,247,0.35)',
				'glow-sm': '0 0 10px rgba(168,85,247,0.5)'
			}
		}
	},
	plugins: []
};
