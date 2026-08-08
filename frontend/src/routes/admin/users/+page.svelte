<script>
	import { adminListUsers, adminCreateUser, adminUpdateUser, adminDeleteUser, adminImportUsers, adminExportUsersUrl, adminExportUserPdfUrl } from '$lib/api.js';
	import { user as currentUser } from '$lib/stores.js';
	import { t } from '$lib/i18n.js';

	let users = [];
	let loading = true;
	let error = '';
	let search = '';
	let searchTimer;

	let showForm = false;
	let editingId = null;
	let form = emptyForm();

	let showImport = false;
	let importFileEl;
	let importBusy = false;
	let importResult = null;

	function emptyForm() {
		return {
			username: '',
			password: '',
			displayName: '',
			role: 'user',
			isActive: true,
			dailyRequestLimit: '',
			dailyTokenLimit: ''
		};
	}

	async function load() {
		loading = true;
		try {
			users = await adminListUsers(search);
		} catch (e) {
			error = e.message;
		} finally {
			loading = false;
		}
	}

	load();

	function handleSearchInput() {
		clearTimeout(searchTimer);
		searchTimer = setTimeout(load, 300);
	}

	function openCreate() {
		editingId = null;
		form = emptyForm();
		showForm = true;
		error = '';
	}

	function openEdit(u) {
		editingId = u.id;
		form = {
			username: u.username,
			password: '',
			displayName: u.displayName,
			role: u.role,
			isActive: u.isActive,
			dailyRequestLimit: u.dailyRequestLimit ?? '',
			dailyTokenLimit: u.dailyTokenLimit ?? ''
		};
		showForm = true;
		error = '';
	}

	async function submitForm() {
		error = '';
		const payload = {
			displayName: form.displayName,
			role: form.role,
			isActive: form.isActive,
			dailyRequestLimit: form.dailyRequestLimit === '' ? null : Number(form.dailyRequestLimit),
			dailyTokenLimit: form.dailyTokenLimit === '' ? null : Number(form.dailyTokenLimit)
		};
		if (form.password) payload.password = form.password;

		try {
			if (editingId) {
				await adminUpdateUser(editingId, payload);
			} else {
				await adminCreateUser({ ...payload, username: form.username, password: form.password });
			}
			showForm = false;
			await load();
		} catch (e) {
			error = e.message;
		}
	}

	async function removeUser(u) {
		if (!confirm($t('users.deleteConfirm'))) return;
		error = '';
		try {
			await adminDeleteUser(u.id);
			await load();
		} catch (e) {
			error = e.message;
		}
	}

	async function toggleBlock(u) {
		error = '';
		try {
			await adminUpdateUser(u.id, { isActive: !u.isActive });
			await load();
		} catch (e) {
			error = e.message;
		}
	}

	async function handleImportFile(event) {
		const file = event.target.files?.[0];
		event.target.value = '';
		if (!file) return;

		importBusy = true;
		importResult = null;
		error = '';
		try {
			importResult = await adminImportUsers(file);
			await load();
		} catch (e) {
			error = e.message;
		} finally {
			importBusy = false;
		}
	}
</script>

<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
	<h2 class="text-lg font-semibold text-slate-200">{$t('users.title')}</h2>
	<div class="flex flex-wrap gap-2">
		<button
			on:click={() => (showImport = true)}
			class="rounded-lg border border-ink-600 px-3 py-2 text-sm text-slate-300 hover:bg-ink-800"
		>
			{$t('users.importButton')}
		</button>
		<a
			href={adminExportUsersUrl()}
			class="rounded-lg border border-ink-600 px-3 py-2 text-sm text-slate-300 hover:bg-ink-800"
		>
			{$t('users.exportButton')}
		</a>
		<button
			on:click={openCreate}
			class="rounded-lg bg-gradient-to-r from-violet-600 to-fuchsia-600 px-4 py-2 text-sm font-medium text-white shadow-glow-sm hover:shadow-glow"
		>
			{$t('users.addButton')}
		</button>
	</div>
</div>

<div class="mb-4">
	<input
		bind:value={search}
		on:input={handleSearchInput}
		placeholder={$t('users.searchPlaceholder')}
		class="w-full max-w-sm rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
	/>
</div>

{#if error}
	<div class="mb-4 rounded-lg border border-red-500/30 bg-red-950/40 px-3 py-2 text-sm text-red-300">{error}</div>
{/if}

{#if showImport}
	<div class="mb-6 rounded-xl border border-ink-700 bg-ink-900 p-4">
		<h3 class="mb-2 text-sm font-semibold text-slate-300">{$t('users.importTitle')}</h3>
		<p class="mb-3 text-xs text-slate-500">{$t('users.importHint')}</p>

		<input
			type="file"
			accept=".xlsx"
			bind:this={importFileEl}
			on:change={handleImportFile}
			class="hidden"
		/>
		<div class="flex items-center gap-2">
			<button
				on:click={() => importFileEl.click()}
				disabled={importBusy}
				class="rounded-lg bg-gradient-to-r from-violet-600 to-fuchsia-600 px-4 py-2 text-sm font-medium text-white shadow-glow-sm hover:shadow-glow disabled:opacity-50"
			>
				{$t('users.importPick')}
			</button>
			<button
				on:click={() => (showImport = false)}
				class="rounded-lg border border-ink-600 px-4 py-2 text-sm text-slate-400 hover:bg-ink-800"
			>
				{$t('close')}
			</button>
		</div>

		{#if importResult}
			<div class="mt-3 text-sm text-slate-300">
				<p>{$t('users.importResult')}: {importResult.created}</p>
				{#if importResult.errors?.length}
					<p class="mt-1 text-red-300">{$t('users.importErrors')}:</p>
					<ul class="list-inside list-disc text-xs text-red-300">
						{#each importResult.errors as err}
							<li>{$t('users.importRow')} {err.row}: {err.message}</li>
						{/each}
					</ul>
				{/if}
			</div>
		{/if}
	</div>
{/if}

{#if showForm}
	<div class="mb-6 rounded-xl border border-ink-700 bg-ink-900 p-4">
		<h3 class="mb-3 text-sm font-semibold text-slate-300">{editingId ? $t('users.editTitle') : $t('users.addTitle')}</h3>
		<form on:submit|preventDefault={submitForm} class="grid grid-cols-1 gap-3 sm:grid-cols-2">
			<label class="text-sm">
				<span class="mb-1 block text-slate-400">{$t('users.username')}</span>
				<input
					bind:value={form.username}
					disabled={!!editingId}
					required
					class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100 disabled:opacity-50"
				/>
			</label>
			<label class="text-sm">
				<span class="mb-1 block text-slate-400">{$t('users.displayName')}</span>
				<input bind:value={form.displayName} class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100" />
			</label>
			<label class="text-sm">
				<span class="mb-1 block text-slate-400">{editingId ? $t('users.passwordHintEdit') : $t('users.password')}</span>
				<input
					type="password"
					bind:value={form.password}
					required={!editingId}
					class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100"
				/>
			</label>
			<label class="text-sm">
				<span class="mb-1 block text-slate-400">{$t('users.role')}</span>
				<select bind:value={form.role} class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100">
					<option value="user">{$t('users.roleUser')}</option>
					<option value="admin">{$t('users.roleAdmin')}</option>
				</select>
			</label>
			<label class="text-sm">
				<span class="mb-1 block text-slate-400">{$t('users.requestLimit')}</span>
				<input
					type="number"
					min="0"
					bind:value={form.dailyRequestLimit}
					class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100"
				/>
			</label>
			<label class="text-sm">
				<span class="mb-1 block text-slate-400">{$t('users.tokenLimit')}</span>
				<input
					type="number"
					min="0"
					bind:value={form.dailyTokenLimit}
					class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100"
				/>
			</label>
			<label class="flex items-center gap-2 text-sm sm:col-span-2">
				<input type="checkbox" bind:checked={form.isActive} class="h-4 w-4 rounded border-ink-600 bg-ink-800 text-violet-600" />
				<span class="text-slate-400">{$t('users.activeLabel')}</span>
			</label>
			<div class="flex gap-2 sm:col-span-2">
				<button
					type="submit"
					class="rounded-lg bg-gradient-to-r from-violet-600 to-fuchsia-600 px-4 py-2 text-sm font-medium text-white shadow-glow-sm hover:shadow-glow"
				>
					{$t('save')}
				</button>
				<button
					type="button"
					on:click={() => (showForm = false)}
					class="rounded-lg border border-ink-600 px-4 py-2 text-sm text-slate-400 hover:bg-ink-800"
				>
					{$t('cancel')}
				</button>
			</div>
		</form>
	</div>
{/if}

{#if loading}
	<p class="text-slate-500">{$t('loading')}</p>
{:else}
	<div class="overflow-x-auto rounded-xl border border-ink-700">
		<table class="w-full text-left text-sm">
			<thead class="bg-ink-900">
				<tr class="text-xs text-slate-500">
					<th class="px-4 py-2.5">{$t('users.colUser')}</th>
					<th class="px-4 py-2.5">{$t('users.colRole')}</th>
					<th class="px-4 py-2.5">{$t('users.colStatus')}</th>
					<th class="px-4 py-2.5">{$t('users.colToday')}</th>
					<th class="px-4 py-2.5">{$t('users.colLimit')}</th>
					<th class="px-4 py-2.5"></th>
				</tr>
			</thead>
			<tbody>
				{#each users as u (u.id)}
					<tr class="border-t border-ink-800 bg-ink-900/40">
						<td class="px-4 py-2.5">
							<div class="text-slate-200">{u.displayName}</div>
							<div class="text-xs text-slate-500">@{u.username}</div>
						</td>
						<td class="px-4 py-2.5">
							<span
								class="rounded-full px-2 py-0.5 text-xs {u.role === 'admin'
									? 'bg-violet-500/15 text-violet-300'
									: 'bg-ink-800 text-slate-400'}"
							>
								{u.role === 'admin' ? $t('users.roleAdmin') : $t('users.roleUser')}
							</span>
						</td>
						<td class="px-4 py-2.5">
							{#if u.isActive}
								<span class="text-xs text-emerald-400">{$t('users.statusActive')}</span>
							{:else}
								<span class="text-xs text-red-400">{$t('users.statusBlocked')}</span>
							{/if}
						</td>
						<td class="px-4 py-2.5 text-slate-300">{u.todayRequests} / {u.todayTokens.toLocaleString()}</td>
						<td class="px-4 py-2.5 text-slate-400">{u.dailyRequestLimit ?? '∞'} / {u.dailyTokenLimit ?? '∞'}</td>
						<td class="whitespace-nowrap px-4 py-2.5 text-right">
							<button on:click={() => openEdit(u)} class="mr-3 text-xs font-medium text-violet-400 hover:text-violet-300">
								{$t('users.editLink')}
							</button>
							<a
								href={adminExportUserPdfUrl(u.id)}
								class="mr-3 text-xs font-medium text-violet-400 hover:text-violet-300"
							>
								{$t('users.exportPdfLink')}
							</a>
							{#if u.id !== $currentUser.id}
								<button on:click={() => toggleBlock(u)} class="mr-3 text-xs font-medium text-amber-400 hover:text-amber-300">
									{u.isActive ? $t('users.blockLink') : $t('users.unblockLink')}
								</button>
								<button on:click={() => removeUser(u)} class="text-xs font-medium text-red-400 hover:text-red-300">
									{$t('users.deleteLink')}
								</button>
							{/if}
						</td>
					</tr>
				{/each}
			</tbody>
		</table>
	</div>
{/if}
