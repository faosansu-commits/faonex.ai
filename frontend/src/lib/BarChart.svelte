<script>
	export let data = []; // [{ label, requests, tokens }]

	$: maxRequests = Math.max(1, ...data.map((d) => d.requests));
	$: maxTokens = Math.max(1, ...data.map((d) => d.tokens));
</script>

<div class="overflow-x-auto">
	<div class="flex items-end gap-3 pb-2" style="min-height:180px">
		{#each data as d}
			<div class="flex flex-col items-center gap-2" style="min-width:44px">
				<div class="flex h-36 items-end gap-1">
					<div
						class="w-3 rounded-t bg-gradient-to-t from-violet-600 to-violet-400"
						style="height:{Math.max(2, (d.requests / maxRequests) * 100)}%"
						title="{d.label}: {d.requests}"
					></div>
					<div
						class="w-3 rounded-t bg-gradient-to-t from-fuchsia-600 to-fuchsia-400"
						style="height:{Math.max(2, (d.tokens / maxTokens) * 100)}%"
						title="{d.label}: {d.tokens}"
					></div>
				</div>
				<span class="text-[10px] text-slate-500">{d.label}</span>
			</div>
		{/each}
		{#if data.length === 0}
			<p class="text-sm text-slate-500">—</p>
		{/if}
	</div>
</div>
