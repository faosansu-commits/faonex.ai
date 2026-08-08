<script>
	import { onMount } from 'svelte';
	import { adminListApiKeys, adminCreateApiKey, adminRevokeApiKey } from '$lib/api.js';
	import { t } from '$lib/i18n.js';

	let keys = [];
	let loading = true;
	let error = '';
	let label = '';
	let creating = false;
	let newKey = null;

	async function load() {
		loading = true;
		try {
			keys = await adminListApiKeys();
		} catch (e) {
			error = e.message;
		} finally {
			loading = false;
		}
	}

	onMount(load);

	async function createKey() {
		if (!label.trim()) return;
		error = '';
		creating = true;
		try {
			newKey = await adminCreateApiKey(label.trim());
			label = '';
			await load();
		} catch (e) {
			error = e.message;
		} finally {
			creating = false;
		}
	}

	async function revoke(id) {
		if (!confirm($t('apiKeys.revokeButton') + '?')) return;
		error = '';
		try {
			await adminRevokeApiKey(id);
			await load();
		} catch (e) {
			error = e.message;
		}
	}

	async function copyKey() {
		if (!newKey) return;
		try {
			await navigator.clipboard.writeText(newKey.rawKey);
		} catch (e) {
			// เพิกเฉยหากคัดลอกไม่สำเร็จ
		}
	}
</script>

<h2 class="mb-1 text-lg font-semibold text-slate-200">{$t('apiKeys.title')}</h2>
<p class="mb-4 text-sm text-slate-500">{$t('apiKeys.description')}</p>

<div class="mb-6 rounded-xl border border-ink-700 bg-ink-900 p-4">
	<h3 class="mb-2 text-sm font-semibold text-slate-300">{$t('apiKeys.stepsTitle')}</h3>
	<ol class="mb-4 list-inside list-decimal space-y-1.5 text-sm text-slate-400">
		<li>{$t('apiKeys.step1')}</li>
		<li>{$t('apiKeys.step2')}</li>
		<li>{$t('apiKeys.step3')}</li>
		<li>{$t('apiKeys.step4')}</li>
	</ol>
	<p class="mb-2 text-xs font-medium text-slate-500">{$t('apiKeys.exampleTitle')}</p>
	<pre class="overflow-x-auto rounded-lg bg-ink-950 px-3 py-2 text-xs text-slate-300"><code
			>curl -X POST https://&lt;your-domain-or-ip&gt;:8095/api/v1/chat \
  -H "Authorization: Bearer fao_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{'{'}"message": "สวัสดี", "mode": "chat"{'}'}'</code
		></pre>
</div>

{#if error}
	<div class="mb-4 rounded-lg border border-red-500/30 bg-red-950/40 px-3 py-2 text-sm text-red-300">{error}</div>
{/if}

{#if newKey}
	<div class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
		<p class="mb-2 text-sm font-medium text-amber-300">⚠ {$t('apiKeys.createdWarning')}</p>
		<div class="flex items-center gap-2">
			<code class="flex-1 overflow-x-auto rounded-lg bg-ink-950 px-3 py-2 text-xs text-slate-200">{newKey.rawKey}</code>
			<button on:click={copyKey} class="shrink-0 rounded-lg border border-ink-600 px-3 py-2 text-xs text-slate-300 hover:bg-ink-800">
				📋
			</button>
			<button on:click={() => (newKey = null)} class="shrink-0 rounded-lg border border-ink-600 px-3 py-2 text-xs text-slate-300 hover:bg-ink-800">
				{$t('close')}
			</button>
		</div>
	</div>
{/if}

<div class="mb-6 flex flex-wrap gap-2">
	<input
		bind:value={label}
		placeholder={$t('apiKeys.labelPlaceholder')}
		class="flex-1 min-w-[200px] rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
	/>
	<button
		on:click={createKey}
		disabled={creating || !label.trim()}
		class="rounded-lg bg-gradient-to-r from-violet-600 to-fuchsia-600 px-4 py-2 text-sm font-medium text-white shadow-glow-sm hover:shadow-glow disabled:opacity-50"
	>
		{$t('apiKeys.createButton')}
	</button>
</div>

{#if loading}
	<p class="text-slate-500">{$t('loading')}</p>
{:else if keys.length === 0}
	<p class="text-sm text-slate-500">{$t('apiKeys.noKeys')}</p>
{:else}
	<div class="overflow-x-auto rounded-xl border border-ink-700">
		<table class="w-full text-left text-sm">
			<thead class="bg-ink-900">
				<tr class="text-xs text-slate-500">
					<th class="px-4 py-2.5">{$t('apiKeys.colLabel')}</th>
					<th class="px-4 py-2.5">{$t('apiKeys.colPrefix')}</th>
					<th class="px-4 py-2.5">{$t('apiKeys.colCreated')}</th>
					<th class="px-4 py-2.5">{$t('apiKeys.colLastUsed')}</th>
					<th class="px-4 py-2.5">{$t('apiKeys.colStatus')}</th>
					<th class="px-4 py-2.5"></th>
				</tr>
			</thead>
			<tbody>
				{#each keys as k (k.id)}
					<tr class="border-t border-ink-800 bg-ink-900/40">
						<td class="px-4 py-2.5 text-slate-200">{k.label}</td>
						<td class="px-4 py-2.5"><code class="text-xs text-slate-400">{k.prefix}…</code></td>
						<td class="px-4 py-2.5 text-slate-400">{k.createdAt}</td>
						<td class="px-4 py-2.5 text-slate-400">{k.lastUsedAt ?? $t('apiKeys.never')}</td>
						<td class="px-4 py-2.5">
							{#if k.isActive}
								<span class="text-xs text-emerald-400">{$t('users.statusActive')}</span>
							{:else}
								<span class="text-xs text-red-400">{$t('users.statusBlocked')}</span>
							{/if}
						</td>
						<td class="px-4 py-2.5 text-right">
							{#if k.isActive}
								<button on:click={() => revoke(k.id)} class="text-xs font-medium text-red-400 hover:text-red-300">
									{$t('apiKeys.revokeButton')}
								</button>
							{/if}
						</td>
					</tr>
				{/each}
			</tbody>
		</table>
	</div>
{/if}
