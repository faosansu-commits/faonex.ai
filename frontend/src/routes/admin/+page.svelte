<script>
	import { onMount, onDestroy } from 'svelte';
	import { adminStats, adminSystem, adminDatabase } from '$lib/api.js';
	import { t } from '$lib/i18n.js';
	import BarChart from '$lib/BarChart.svelte';
	import PieChart from '$lib/PieChart.svelte';

	let stats = null;
	let system = null;
	let database = null;
	let loading = true;
	let period = 'day';
	let pollHandle;

	async function loadStats() {
		try {
			stats = await adminStats(period);
		} catch (e) {
			// เพิกเฉย จะลองใหม่รอบถัดไป
		}
	}

	async function loadSystem() {
		try {
			system = await adminSystem();
		} catch (e) {
			// เพิกเฉย จะลองใหม่รอบถัดไป
		}
	}

	async function loadDatabase() {
		try {
			database = await adminDatabase();
		} catch (e) {
			// เพิกเฉย จะลองใหม่รอบถัดไป
		}
	}

	async function changePeriod(next) {
		period = next;
		await loadStats();
	}

	onMount(async () => {
		await Promise.all([loadStats(), loadSystem(), loadDatabase()]);
		loading = false;
		pollHandle = setInterval(() => {
			loadSystem();
			loadDatabase();
		}, 15000);
	});

	onDestroy(() => {
		if (pollHandle) clearInterval(pollHandle);
	});

	function barWidth(percent) {
		return `width:${Math.min(100, Math.max(0, percent ?? 0))}%`;
	}

	function barColor(percent, warnAt) {
		if (percent === null || percent === undefined) return 'bg-slate-600';
		if (percent >= warnAt) return 'bg-red-500';
		if (percent >= warnAt - 20) return 'bg-amber-500';
		return 'bg-gradient-to-r from-violet-500 to-fuchsia-500';
	}

	function formatBucket(bucket, p) {
		if (p === 'day') return bucket.slice(5); // MM-DD
		return bucket;
	}

	$: seriesData = (stats?.series ?? []).map((s) => ({
		label: formatBucket(s.bucket, period),
		requests: s.requests,
		tokens: s.tokens
	}));

	$: pieData = (stats?.modeBreakdown ?? []).map((m) => ({
		label: m.mode === 'code' ? $t('chat.modeCode') : m.mode === 'topic' ? $t('dashboard.modeTopic') : $t('chat.modeChat'),
		value: m.tokens,
		color: m.mode === 'code' ? '#e879f9' : m.mode === 'topic' ? '#22d3ee' : '#a855f7'
	}));

	$: hasWarning =
		system?.warnings?.ollama || system?.warnings?.cpu || system?.warnings?.memory || system?.warnings?.disk || system?.warnings?.database;
</script>

{#if loading}
	<p class="text-slate-500">{$t('loading')}</p>
{:else}
	{#if hasWarning}
		<div class="mb-4 rounded-xl border border-red-500/30 bg-red-950/30 p-4 text-sm text-red-300">
			<p class="mb-1 font-semibold">{$t('dashboard.warningsTitle')}</p>
			<ul class="list-inside list-disc space-y-1">
				{#if system?.warnings?.ollama}
					<li>{$t('dashboard.warningOllama')}</li>
				{/if}
				{#if system?.warnings?.database}
					<li>{$t('dashboard.warningDatabase')}</li>
				{/if}
				{#if system?.warnings?.cpu}
					<li>{$t('dashboard.warningCpu')}</li>
				{/if}
				{#if system?.warnings?.memory}
					<li>{$t('dashboard.warningMemory')}</li>
				{/if}
				{#if system?.warnings?.disk}
					<li>{$t('dashboard.warningDisk')}</li>
				{/if}
			</ul>
		</div>
	{/if}

	<div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
		<div class="rounded-xl border border-ink-700 bg-ink-900 p-4">
			<p class="text-xs text-slate-500">{$t('dashboard.totalUsers')}</p>
			<p class="mt-1 text-2xl font-bold text-slate-100">{stats.totalUsers}</p>
		</div>
		<div class="rounded-xl border border-ink-700 bg-ink-900 p-4">
			<p class="text-xs text-slate-500">{$t('dashboard.totalConversations')}</p>
			<p class="mt-1 text-2xl font-bold text-slate-100">{stats.totalConversations}</p>
		</div>
		<div class="rounded-xl border border-ink-700 bg-ink-900 p-4">
			<p class="text-xs text-slate-500">{$t('dashboard.requestsToday')}</p>
			<p class="mt-1 text-2xl font-bold text-violet-300">{stats.todayRequests}</p>
		</div>
		<div class="rounded-xl border border-ink-700 bg-ink-900 p-4">
			<p class="text-xs text-slate-500">{$t('dashboard.tokensToday')}</p>
			<p class="mt-1 text-2xl font-bold text-violet-300">{stats.todayTokens.toLocaleString()}</p>
		</div>
		<div class="rounded-xl border border-ink-700 bg-ink-900 p-4">
			<p class="text-xs text-slate-500">{$t('dashboard.allTimeRequests')}</p>
			<p class="mt-1 text-2xl font-bold text-slate-100">{stats.allTime.requests.toLocaleString()}</p>
		</div>
		<div class="rounded-xl border border-ink-700 bg-ink-900 p-4">
			<p class="text-xs text-slate-500">{$t('dashboard.allTimeTokens')}</p>
			<p class="mt-1 text-2xl font-bold text-slate-100">{stats.allTime.tokens.toLocaleString()}</p>
		</div>
	</div>

	<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
		<div class="rounded-xl border border-ink-700 bg-ink-900 p-4">
			<div class="mb-2 flex items-center justify-between text-sm">
				<span class="text-slate-400">{$t('dashboard.cpu')}</span>
				<span class="font-medium text-slate-200">{system?.cpu?.percent ?? '-'}%</span>
			</div>
			<div class="h-2 overflow-hidden rounded-full bg-ink-800">
				<div class="h-full transition-all {barColor(system?.cpu?.percent, 90)}" style={barWidth(system?.cpu?.percent)}></div>
			</div>
		</div>
		<div class="rounded-xl border border-ink-700 bg-ink-900 p-4">
			<div class="mb-2 flex items-center justify-between text-sm">
				<span class="text-slate-400">{$t('dashboard.ram')}</span>
				<span class="font-medium text-slate-200">
					{system?.memory?.percent ?? '-'}% ({system?.memory?.usedMb ?? 0} / {system?.memory?.totalMb ?? 0} MB)
				</span>
			</div>
			<div class="h-2 overflow-hidden rounded-full bg-ink-800">
				<div class="h-full transition-all {barColor(system?.memory?.percent, 90)}" style={barWidth(system?.memory?.percent)}></div>
			</div>
		</div>
		<div class="rounded-xl border border-ink-700 bg-ink-900 p-4">
			<div class="mb-2 flex items-center justify-between text-sm">
				<span class="text-slate-400">{$t('dashboard.disk')}</span>
				<span class="font-medium text-slate-200">
					{system?.disk?.percent ?? '-'}% ({system?.disk?.usedGb ?? 0} / {system?.disk?.totalGb ?? 0} GB)
				</span>
			</div>
			<div class="h-2 overflow-hidden rounded-full bg-ink-800">
				<div class="h-full transition-all {barColor(system?.disk?.percent, 85)}" style={barWidth(system?.disk?.percent)}></div>
			</div>
		</div>
	</div>

	<div class="mb-6 rounded-xl border border-ink-700 bg-ink-900 p-4">
		<div class="mb-3 flex flex-wrap items-center justify-between gap-2">
			<p class="text-sm font-semibold text-slate-300">{$t('dashboard.barChartTitle')}</p>
			<div class="flex items-center gap-1 rounded-lg border border-ink-700 bg-ink-800 p-1">
				{#each [['day', $t('dashboard.periodDay')], ['month', $t('dashboard.periodMonth')], ['year', $t('dashboard.periodYear')]] as [value, label]}
					<button
						on:click={() => changePeriod(value)}
						class="rounded-lg px-3 py-1 text-xs font-medium transition {period === value
							? 'bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white shadow-glow-sm'
							: 'text-slate-400 hover:text-slate-200'}"
					>
						{label}
					</button>
				{/each}
			</div>
		</div>
		<div class="mb-2 flex items-center gap-4 text-xs text-slate-400">
			<span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-violet-500"></span>{$t('dashboard.colRequests')}</span>
			<span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-fuchsia-400"></span>{$t('dashboard.colTokens')}</span>
		</div>
		<BarChart data={seriesData} />
	</div>

	<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
		<div class="rounded-xl border border-ink-700 bg-ink-900 p-4">
			<p class="mb-3 text-sm font-semibold text-slate-300">{$t('dashboard.pieChartTitle')}</p>
			<PieChart data={pieData} />
		</div>

		<div class="rounded-xl border border-ink-700 bg-ink-900 p-4">
			<p class="mb-3 text-sm font-semibold text-slate-300">{$t('dashboard.servicesTitle')}</p>
			<div class="flex flex-col gap-2">
				{#each system?.services ?? [] as svc}
					<div class="flex items-center justify-between rounded-lg border border-ink-800 bg-ink-950/40 px-3 py-2 text-sm">
						<span class="text-slate-300">{svc.label}</span>
						{#if svc.online}
							<span class="flex items-center gap-1.5 text-xs text-emerald-400">
								<span class="h-2 w-2 rounded-full bg-emerald-400"></span> OK
							</span>
						{:else}
							<span class="flex items-center gap-1.5 text-xs text-red-400">
								<span class="h-2 w-2 rounded-full bg-red-400"></span> DOWN
							</span>
						{/if}
					</div>
				{/each}
			</div>
		</div>
	</div>

	{#if database}
		<div class="mb-6 rounded-xl border border-ink-700 bg-ink-900 p-4">
			<div class="mb-3 flex items-center justify-between">
				<p class="text-sm font-semibold text-slate-300">{$t('dashboard.dbTitle')}</p>
				<span class="text-xs text-slate-500">{$t('dashboard.dbSize')}: {database.fileSizeMb} MB</span>
			</div>
			<div class="overflow-x-auto">
				<table class="w-full text-left text-sm">
					<thead>
						<tr class="text-xs text-slate-500">
							<th class="pb-2">{$t('dashboard.dbTable')}</th>
							<th class="pb-2">{$t('dashboard.dbRows')}</th>
						</tr>
					</thead>
					<tbody>
						{#each database.tables as tbl}
							<tr class="border-t border-ink-800">
								<td class="py-1.5 text-slate-200">{tbl.table}</td>
								<td class="py-1.5 text-slate-300">{tbl.rows.toLocaleString()}</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</div>
		</div>
	{/if}

	<div class="rounded-xl border border-ink-700 bg-ink-900 p-4">
		<p class="mb-3 text-sm font-semibold text-slate-300">{$t('dashboard.topUsersTitle')}</p>
		{#if stats.topUsersToday.length === 0}
			<p class="text-sm text-slate-500">{$t('dashboard.noUsageToday')}</p>
		{:else}
			<div class="overflow-x-auto">
				<table class="w-full text-left text-sm">
					<thead>
						<tr class="text-xs text-slate-500">
							<th class="pb-2">{$t('dashboard.colUser')}</th>
							<th class="pb-2">{$t('dashboard.colRequests')}</th>
							<th class="pb-2">{$t('dashboard.colTokens')}</th>
						</tr>
					</thead>
					<tbody>
						{#each stats.topUsersToday as u}
							<tr class="border-t border-ink-800">
								<td class="py-1.5 text-slate-200">{u.displayName} <span class="text-slate-500">@{u.username}</span></td>
								<td class="py-1.5 text-slate-300">{u.requests}</td>
								<td class="py-1.5 text-slate-300">{u.tokens.toLocaleString()}</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</div>
		{/if}
	</div>
{/if}
