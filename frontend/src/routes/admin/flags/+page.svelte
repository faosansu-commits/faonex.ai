<script>
	import { onMount } from 'svelte';
	import { adminListFlags } from '$lib/api.js';
	import { t } from '$lib/i18n.js';

	let flags = [];
	let loading = true;
	let error = '';

	onMount(async () => {
		try {
			flags = await adminListFlags();
		} catch (e) {
			error = e.message;
		} finally {
			loading = false;
		}
	});
</script>

<h2 class="mb-1 text-lg font-semibold text-slate-200">{$t('flags.title')}</h2>
<p class="mb-4 text-sm text-slate-500">{$t('flags.description')}</p>

{#if error}
	<div class="mb-4 rounded-lg border border-red-500/30 bg-red-950/40 px-3 py-2 text-sm text-red-300">{error}</div>
{/if}

{#if loading}
	<p class="text-slate-500">{$t('loading')}</p>
{:else if flags.length === 0}
	<p class="text-sm text-slate-500">{$t('flags.noFlags')}</p>
{:else}
	<div class="flex flex-col gap-3">
		{#each flags as f (f.id)}
			<div class="rounded-xl border border-red-500/20 bg-red-950/10 p-4">
				<div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
					<span>{f.username} · {f.createdAt}</span>
					<span class="rounded-full bg-red-500/15 px-2 py-0.5 text-red-300">{$t('flags.matchedLabel')}: {f.matchedKeywords}</span>
				</div>
				<p class="whitespace-pre-wrap text-sm text-slate-300">{f.content}</p>
			</div>
		{/each}
	</div>
{/if}
