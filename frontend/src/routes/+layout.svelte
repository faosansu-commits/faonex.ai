<script>
	import '../app.css';
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { user, authChecked } from '$lib/stores.js';
	import { fetchMe } from '$lib/api.js';
	import Logo from '$lib/Logo.svelte';

	const publicPaths = ['/login', '/register'];

	onMount(async () => {
		try {
			const me = await fetchMe();
			user.set(me);
		} catch (e) {
			user.set(null);
		} finally {
			authChecked.set(true);
		}
	});

	$: if ($authChecked) {
		const path = $page.url.pathname;
		if (!$user && !publicPaths.includes(path)) {
			goto('/login');
		} else if ($user && publicPaths.includes(path)) {
			goto('/');
		}
	}
</script>

{#if $authChecked}
	<slot />
{:else}
	<div class="flex h-screen flex-col items-center justify-center gap-4 bg-ink-950">
		<Logo size={56} />
		<p class="text-sm text-slate-500">กำลังโหลด...</p>
	</div>
{/if}
