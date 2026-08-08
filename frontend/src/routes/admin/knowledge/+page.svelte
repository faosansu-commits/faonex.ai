<script>
	import { onMount } from 'svelte';
	import {
		adminListKnowledgeTopics,
		adminCreateKnowledgeTopic,
		adminUpdateKnowledgeTopic,
		adminDeleteKnowledgeTopic,
		adminListKnowledgeDocuments,
		adminUploadKnowledgeDocument,
		adminAddKnowledgeText,
		adminDeleteKnowledgeDocument,
		adminKnowledgeTemplateUrl
	} from '$lib/api.js';
	import { t } from '$lib/i18n.js';

	let topics = [];
	let loading = true;
	let error = '';

	let showModal = false;
	let editing = null;
	let form = { name: '', keywords: '', fallbackMessage: '', isActive: true };
	let saving = false;

	let expandedTopicId = null;
	let documents = [];
	let docsLoading = false;
	let uploading = false;
	let fileInput;

	let textForm = { title: '', content: '' };
	let savingText = false;

	async function load() {
		loading = true;
		try {
			topics = await adminListKnowledgeTopics();
		} catch (e) {
			error = e.message;
		} finally {
			loading = false;
		}
	}

	onMount(load);

	function openAdd() {
		editing = null;
		form = { name: '', keywords: '', fallbackMessage: '', isActive: true };
		showModal = true;
	}

	function openEdit(topic) {
		editing = topic;
		form = {
			name: topic.name,
			keywords: topic.keywords,
			fallbackMessage: topic.fallbackMessage,
			isActive: topic.isActive
		};
		showModal = true;
	}

	async function loadDocuments(topicId) {
		docsLoading = true;
		try {
			documents = await adminListKnowledgeDocuments(topicId);
		} catch (e) {
			error = e.message;
		} finally {
			docsLoading = false;
		}
	}

	async function saveTopic() {
		error = '';
		saving = true;
		try {
			if (editing) {
				await adminUpdateKnowledgeTopic(editing.id, form);
				showModal = false;
				await load();
			} else {
				const created = await adminCreateKnowledgeTopic(form);
				showModal = false;
				await load();
				// เปิดแผงเพิ่มเนื้อหาให้ทันทีที่สร้างหัวข้อใหม่เสร็จ ไม่ต้องกดเพิ่มอีกขั้นตอน
				expandedTopicId = created.id;
				textForm = { title: '', content: '' };
				await loadDocuments(created.id);
			}
		} catch (e) {
			error = e.message;
		} finally {
			saving = false;
		}
	}

	async function removeTopic(topic) {
		if (!confirm($t('knowledge.deleteConfirm'))) return;
		error = '';
		try {
			await adminDeleteKnowledgeTopic(topic.id);
			if (expandedTopicId === topic.id) expandedTopicId = null;
			await load();
		} catch (e) {
			error = e.message;
		}
	}

	async function toggleContent(topic) {
		if (expandedTopicId === topic.id) {
			expandedTopicId = null;
			return;
		}
		expandedTopicId = topic.id;
		textForm = { title: '', content: '' };
		await loadDocuments(topic.id);
	}

	async function saveText() {
		if (!expandedTopicId || !textForm.content.trim()) return;
		error = '';
		savingText = true;
		try {
			await adminAddKnowledgeText(expandedTopicId, textForm);
			textForm = { title: '', content: '' };
			await loadDocuments(expandedTopicId);
			await load();
		} catch (e) {
			error = e.message;
		} finally {
			savingText = false;
		}
	}

	async function handleUpload(e) {
		const file = e.target.files?.[0];
		if (!file || !expandedTopicId) return;
		error = '';
		uploading = true;
		try {
			await adminUploadKnowledgeDocument(expandedTopicId, file);
			await loadDocuments(expandedTopicId);
			await load();
		} catch (err) {
			error = err.message;
		} finally {
			uploading = false;
			if (fileInput) fileInput.value = '';
		}
	}

	async function removeDocument(doc) {
		if (!confirm($t('knowledge.deleteDocConfirm'))) return;
		error = '';
		try {
			await adminDeleteKnowledgeDocument(doc.id);
			await loadDocuments(expandedTopicId);
			await load();
		} catch (e) {
			error = e.message;
		}
	}
</script>

<h2 class="mb-1 text-lg font-semibold text-slate-200">{$t('knowledge.title')}</h2>
<p class="mb-4 text-sm text-slate-500">{$t('knowledge.description')}</p>

{#if error}
	<div class="mb-4 rounded-lg border border-red-500/30 bg-red-950/40 px-3 py-2 text-sm text-red-300">{error}</div>
{/if}

<div class="mb-4 flex flex-wrap gap-2">
	<button
		on:click={openAdd}
		class="rounded-lg bg-gradient-to-r from-violet-600 to-fuchsia-600 px-4 py-2 text-sm font-medium text-white shadow-glow-sm hover:shadow-glow"
	>
		{$t('knowledge.addButton')}
	</button>
	<a
		href={adminKnowledgeTemplateUrl()}
		class="rounded-lg border border-ink-600 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-ink-800"
	>
		{$t('knowledge.downloadTemplateButton')}
	</a>
</div>

{#if showModal}
	<div class="mb-6 rounded-xl border border-ink-700 bg-ink-900 p-4">
		<h3 class="mb-3 text-sm font-semibold text-slate-300">
			{editing ? $t('knowledge.editTitle') : $t('knowledge.addTitle')}
		</h3>
		<form on:submit|preventDefault={saveTopic} class="grid grid-cols-1 gap-3">
			<label class="text-sm">
				<span class="mb-1 block text-slate-400">{$t('knowledge.name')}</span>
				<input
					bind:value={form.name}
					placeholder={$t('knowledge.namePlaceholder')}
					required
					class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500"
				/>
			</label>
			<label class="text-sm">
				<span class="mb-1 block text-slate-400">{$t('knowledge.keywords')}</span>
				<input
					bind:value={form.keywords}
					placeholder={$t('knowledge.keywordsPlaceholder')}
					required
					class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500"
				/>
			</label>
			<label class="text-sm">
				<span class="mb-1 block text-slate-400">{$t('knowledge.fallbackMessage')}</span>
				<textarea
					bind:value={form.fallbackMessage}
					placeholder={$t('knowledge.fallbackPlaceholder')}
					rows="3"
					class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500"
				></textarea>
			</label>
			<label class="flex items-center gap-2 text-sm">
				<input type="checkbox" bind:checked={form.isActive} class="h-4 w-4 rounded border-ink-600 bg-ink-800 text-violet-600" />
				<span class="text-slate-400">{$t('knowledge.activeLabel')}</span>
			</label>
			<div class="flex gap-2">
				<button
					type="submit"
					disabled={saving || !form.name.trim() || !form.keywords.trim()}
					class="rounded-lg bg-gradient-to-r from-violet-600 to-fuchsia-600 px-4 py-2 text-sm font-medium text-white shadow-glow-sm hover:shadow-glow disabled:opacity-50"
				>
					{$t('save')}
				</button>
				<button
					type="button"
					on:click={() => (showModal = false)}
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
{:else if topics.length === 0}
	<p class="text-sm text-slate-500">{$t('knowledge.noTopics')}</p>
{:else}
	<div class="overflow-x-auto rounded-xl border border-ink-700">
		<table class="w-full text-left text-sm">
			<thead class="bg-ink-900">
				<tr class="text-xs text-slate-500">
					<th class="px-4 py-2.5">{$t('knowledge.colName')}</th>
					<th class="px-4 py-2.5">{$t('knowledge.colKeywords')}</th>
					<th class="px-4 py-2.5">{$t('knowledge.colDocs')}</th>
					<th class="px-4 py-2.5">{$t('knowledge.colStatus')}</th>
					<th class="px-4 py-2.5"></th>
				</tr>
			</thead>
			<tbody>
				{#each topics as topic (topic.id)}
					<tr class="border-t border-ink-800 bg-ink-900/40">
						<td class="px-4 py-2.5 text-slate-200">{topic.name}</td>
						<td class="max-w-xs truncate px-4 py-2.5 text-slate-400" title={topic.keywords}>{topic.keywords}</td>
						<td class="px-4 py-2.5 text-slate-400">{topic.documentCount} ({topic.chunkCount})</td>
						<td class="px-4 py-2.5">
							{#if topic.isActive}
								<span class="text-xs text-emerald-400">{$t('users.statusActive')}</span>
							{:else}
								<span class="text-xs text-red-400">{$t('users.statusBlocked')}</span>
							{/if}
						</td>
						<td class="whitespace-nowrap px-4 py-2.5 text-right">
							<button on:click={() => toggleContent(topic)} class="mr-3 text-xs font-medium text-violet-400 hover:text-violet-300">
								{expandedTopicId === topic.id ? `▲ ${$t('close')}` : `▼ ${$t('knowledge.manageDocsLink')}`}
							</button>
							<button on:click={() => openEdit(topic)} class="mr-3 text-xs font-medium text-slate-300 hover:text-slate-100">
								{$t('edit')}
							</button>
							<button on:click={() => removeTopic(topic)} class="text-xs font-medium text-red-400 hover:text-red-300">
								{$t('delete')}
							</button>
						</td>
					</tr>
					{#if expandedTopicId === topic.id}
						<tr class="border-t border-ink-800 bg-ink-950/60">
							<td colspan="5" class="p-4">
								<div class="mb-4 rounded-xl border border-ink-700 bg-ink-900 p-4">
									<h4 class="mb-1 text-sm font-semibold text-slate-300">{$t('knowledge.addTextTitle')}</h4>
									<p class="mb-3 text-xs text-slate-500">{$t('knowledge.addTextHint')}</p>

									<div class="mb-3">
										<label class="mb-1 block text-xs text-slate-400">{$t('knowledge.textTitleLabel')}</label>
										<input
											bind:value={textForm.title}
											placeholder={$t('knowledge.textTitlePlaceholder')}
											class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500"
										/>
									</div>
									<div class="mb-3">
										<label class="mb-1 block text-xs text-slate-400">{$t('knowledge.textContentLabel')}</label>
										<textarea
											bind:value={textForm.content}
											placeholder={$t('knowledge.textContentPlaceholder')}
											rows="5"
											class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500"
										></textarea>
									</div>
									<div class="flex items-center gap-3">
										<button
											on:click={saveText}
											disabled={savingText || !textForm.content.trim()}
											class="rounded-lg bg-gradient-to-r from-violet-600 to-fuchsia-600 px-4 py-2 text-sm font-medium text-white shadow-glow-sm hover:shadow-glow disabled:opacity-50"
										>
											{$t('knowledge.saveTextButton')}
										</button>
										{#if savingText}
											<span class="text-xs text-slate-400">{$t('knowledge.savingText')}</span>
										{/if}
									</div>
								</div>

								<p class="mb-2 text-xs text-slate-500">{$t('knowledge.orUploadPdf')}</p>
								<div class="mb-4 flex flex-wrap items-center gap-3">
									<label
										class="cursor-pointer rounded-lg border border-ink-600 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-ink-800 {uploading
											? 'pointer-events-none opacity-50'
											: ''}"
									>
										{$t('knowledge.uploadButton')}
										<input
											bind:this={fileInput}
											type="file"
											accept="application/pdf"
											class="hidden"
											on:change={handleUpload}
											disabled={uploading}
										/>
									</label>
									{#if uploading}
										<span class="text-sm text-slate-400">{$t('knowledge.uploading')}</span>
									{/if}
								</div>

								{#if docsLoading}
									<p class="text-slate-500">{$t('loading')}</p>
								{:else if documents.length === 0}
									<p class="text-sm text-slate-500">{$t('knowledge.noDocuments')}</p>
								{:else}
									<div class="overflow-x-auto rounded-xl border border-ink-700">
										<table class="w-full text-left text-sm">
											<thead class="bg-ink-900">
												<tr class="text-xs text-slate-500">
													<th class="px-4 py-2.5">{$t('knowledge.colFilename')}</th>
													<th class="px-4 py-2.5">{$t('knowledge.colChunks')}</th>
													<th class="px-4 py-2.5">{$t('knowledge.colUploaded')}</th>
													<th class="px-4 py-2.5"></th>
												</tr>
											</thead>
											<tbody>
												{#each documents as doc (doc.id)}
													<tr class="border-t border-ink-800 bg-ink-900/40">
														<td class="px-4 py-2.5 text-slate-200">
															<span
																class="mr-1"
																title={doc.filename.toLowerCase().endsWith('.pdf') ? 'PDF' : $t('knowledge.addTextTitle')}
															>
																{doc.filename.toLowerCase().endsWith('.pdf') ? '📄' : '✏️'}
															</span>{doc.filename}
														</td>
														<td class="px-4 py-2.5 text-slate-400">{doc.chunkCount}</td>
														<td class="px-4 py-2.5 text-slate-400">{doc.createdAt}</td>
														<td class="px-4 py-2.5 text-right">
															<button
																on:click={() => removeDocument(doc)}
																class="text-xs font-medium text-red-400 hover:text-red-300"
															>
																{$t('delete')}
															</button>
														</td>
													</tr>
												{/each}
											</tbody>
										</table>
									</div>
								{/if}
							</td>
						</tr>
					{/if}
				{/each}
			</tbody>
		</table>
	</div>
{/if}
