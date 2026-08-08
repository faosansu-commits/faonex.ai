<script>
	export let data = []; // [{ label, value, color }]

	$: total = data.reduce((sum, d) => sum + d.value, 0);
	$: gradient = (() => {
		if (total <= 0) return null;
		let acc = 0;
		const stops = data.map((d) => {
			const start = (acc / total) * 100;
			acc += d.value;
			const end = (acc / total) * 100;
			return `${d.color} ${start}% ${end}%`;
		});
		return `conic-gradient(${stops.join(', ')})`;
	})();
</script>

<div class="flex flex-wrap items-center gap-6">
	<div class="h-36 w-36 shrink-0 rounded-full" style="background:{gradient ?? 'rgb(var(--c-ink-700))'}"></div>
	<div class="flex flex-col gap-1.5 text-sm">
		{#each data as d}
			<div class="flex items-center gap-2">
				<span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:{d.color}"></span>
				<span class="text-slate-300">{d.label}</span>
				<span class="text-slate-500">({total > 0 ? Math.round((d.value / total) * 100) : 0}%)</span>
			</div>
		{/each}
		{#if data.length === 0 || total === 0}
			<p class="text-sm text-slate-500">—</p>
		{/if}
	</div>
</div>
