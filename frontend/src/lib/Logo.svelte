<script>
	import { theme } from '$lib/theme.js';

	export let size = 40;

	$: isLight = $theme === 'light';
</script>

<div class="relative shrink-0" style="width:{size}px;height:{size}px">
	<div
		class="glow-pulse absolute inset-0 rounded-2xl opacity-70 blur-md
		{isLight ? 'bg-gradient-to-br from-slate-800 via-black to-slate-700' : 'bg-gradient-to-br from-violet-500 via-fuchsia-400 to-white'}"
	></div>
	<div
		class="relative flex h-full w-full items-center justify-center overflow-hidden rounded-2xl bg-ink-950 shadow-glow ring-1
		{isLight ? 'ring-black/25' : 'ring-white/20'}"
	>
		<svg viewBox="0 0 64 64" style="width:{size * 0.8}px;height:{size * 0.8}px">
			{#if isLight}
				<g stroke="#ffffff" stroke-width="1.75" stroke-linejoin="round">
					<polygon points="32,33 32,8 54,33" fill="#111111" />
					<polygon points="32,33 54,33 32,58" fill="#000000" />
					<polygon points="32,33 32,58 10,33" fill="#262626" />
					<polygon points="32,33 10,33 32,8" fill="#000000" />
				</g>
			{:else}
				<g stroke="#07070c" stroke-width="1" stroke-linejoin="round">
					<polygon points="32,33 32,8 54,33" fill="#ede9fe" />
					<polygon points="32,33 54,33 32,58" fill="#a855f7" />
					<polygon points="32,33 32,58 10,33" fill="#6d28d9" />
					<polygon points="32,33 10,33 32,8" fill="#e879f9" />
				</g>
			{/if}
		</svg>
	</div>
</div>
