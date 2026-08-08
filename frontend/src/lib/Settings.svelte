<script>
	import { theme } from '$lib/theme.js';
	import { locale, t } from '$lib/i18n.js';

	let open = false;
</script>

<div class="relative">
	<button
		on:click={() => (open = !open)}
		title={$t('settings.title')}
		class="rounded-lg p-2 text-sm text-slate-500 transition hover:bg-ink-800 hover:text-slate-300"
	>
		⚙️
	</button>

	{#if open}
		<div class="fixed inset-0 z-40" role="presentation" on:click={() => (open = false)}></div>
		<div
			class="absolute right-0 top-full z-50 mt-2 w-56 rounded-xl border border-ink-700 bg-ink-900 p-4 shadow-glow-lg"
		>
			<p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{$t('settings.title')}</p>

			<p class="mb-1.5 text-xs text-slate-400">{$t('settings.theme')}</p>
			<div class="mb-4 flex items-center gap-1 rounded-lg border border-ink-700 bg-ink-800 p-1">
				<button
					on:click={() => theme.set('dark')}
					class="flex-1 rounded-lg px-2 py-1.5 text-xs font-medium transition {$theme === 'dark'
						? 'bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white shadow-glow-sm'
						: 'text-slate-400 hover:text-slate-200'}"
				>
					🌙 {$t('settings.themeDark')}
				</button>
				<button
					on:click={() => theme.set('light')}
					class="flex-1 rounded-lg px-2 py-1.5 text-xs font-medium transition {$theme === 'light'
						? 'bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white shadow-glow-sm'
						: 'text-slate-400 hover:text-slate-200'}"
				>
					☀️ {$t('settings.themeLight')}
				</button>
			</div>

			<p class="mb-1.5 text-xs text-slate-400">{$t('settings.language')}</p>
			<div class="flex items-center gap-1 rounded-lg border border-ink-700 bg-ink-800 p-1">
				<button
					on:click={() => locale.set('th')}
					class="flex-1 rounded-lg px-2 py-1.5 text-xs font-medium transition {$locale === 'th'
						? 'bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white shadow-glow-sm'
						: 'text-slate-400 hover:text-slate-200'}"
				>
					ไทย
				</button>
				<button
					on:click={() => locale.set('en')}
					class="flex-1 rounded-lg px-2 py-1.5 text-xs font-medium transition {$locale === 'en'
						? 'bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white shadow-glow-sm'
						: 'text-slate-400 hover:text-slate-200'}"
				>
					English
				</button>
			</div>
		</div>
	{/if}
</div>
