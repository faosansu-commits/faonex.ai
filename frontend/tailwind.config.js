/** @type {import('tailwindcss').Config} */
export default {
	content: ['./src/**/*.{html,js,svelte,ts}'],
	theme: {
		extend: {
			colors: {
				ink: {
					950: '#07070c',
					900: '#0d0d16',
					850: '#111120',
					800: '#15152a',
					700: '#1f1f38',
					600: '#2b2b4a'
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
