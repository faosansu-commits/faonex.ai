<script>
	import { user } from '$lib/stores.js';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { t } from '$lib/i18n.js';
	import Logo from '$lib/Logo.svelte';
	import Settings from '$lib/Settings.svelte';

	$: if ($user && $user.role !== 'admin') {
		goto('/');
	}

	$: tabs = [
		{ href: '/admin', label: $t('admin.navOverview') },
		{ href: '/admin/users', label: $t('admin.navUsers') },
		{ href: '/admin/flags', label: $t('admin.navFlags') },
		{ href: '/admin/api-keys', label: $t('admin.navApiKeys') },
		{ href: '/admin/knowledge', label: $t('admin.navKnowledge') }
	];
</script>

{#if $user?.role === 'admin'}
	<div class="flex h-screen flex-col bg-ink-950">
		<header class="flex items-center gap-3 border-b border-ink-700 bg-ink-900/80 px-4 py-4 backdrop-blur-xl sm:px-6">
			<Logo size={36} />
			<div class="min-w-0 flex-1">
				<h1 class="truncate text-lg font-bold leading-tight">
					<span class="brand-text">FAONEX</span><span class="text-violet-400">.AI</span>
					<span class="ml-2 text-sm font-normal text-slate-400">{$t('admin.panelTitle')}</span>
				</h1>
			</div>
			<Settings />
			<a href="/" class="shrink-0 text-sm font-medium text-slate-400 hover:text-violet-300">{$t('admin.backToChat')}</a>
		</header>

		<nav class="flex gap-1 overflow-x-auto border-b border-ink-700 bg-ink-900/60 px-4 py-2 sm:px-6">
			{#each tabs as tab}
				<a
					href={tab.href}
					class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium transition
					{$page.url.pathname === tab.href
						? 'bg-violet-500/15 text-violet-300'
						: 'text-slate-400 hover:bg-ink-800 hover:text-slate-200'}"
				>
					{tab.label}
				</a>
			{/each}
		</nav>

		<main class="flex-1 overflow-y-auto p-4 sm:p-6">
			<slot />
		</main>
	</div>
{/if}
