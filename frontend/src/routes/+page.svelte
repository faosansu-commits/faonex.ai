<script>
	import { onMount, tick } from 'svelte';
	import { goto } from '$app/navigation';
	import { user } from '$lib/stores.js';
	import { t } from '$lib/i18n.js';
	import Logo from '$lib/Logo.svelte';
	import Settings from '$lib/Settings.svelte';
	import ChatInputBar from '$lib/ChatInputBar.svelte';
	import {
		logout,
		listConversations,
		createConversation,
		getMessages,
		deleteConversation,
		streamChat,
		fetchUsage,
		fetchModels,
		pullModel
	} from '$lib/api.js';

	let orgName = 'ผู้ช่วย AI องค์กร';
	let conversations = [];
	let currentConversationId = null;
	let usage = null; // { requests, requestLimit, tokens, tokenLimit }
	let messages = []; // { role: 'user' | 'assistant', content: string, error?: boolean }
	let input = '';
	let loading = false;
	let streaming = false;
	let abortController = null;
	let mode = 'chat'; // 'chat' | 'code'
	let sidebarOpen = false; // มือถือ: ลิ้นชักเปิด/ปิด
	let sidebarCollapsed = false; // เดสก์ท็อป: ยุบ/ขยาย
	let searchQuery = '';
	let autoSpeak = false;
	let voiceSupported = false;
	let chatEl;

	let models = []; // จาก /api/models: [{ id, label, vendor, sizeGb, kind, installed }]
	let selectedModel = 'llama3.2';
	let modelBusy = false;
	let modelBusyLabel = '';
	let attachedImage = null; // { name, dataUrl }
	let attachedFile = null; // { name, text }

	$: filteredConversations = searchQuery.trim()
		? conversations.filter((c) => c.title.toLowerCase().includes(searchQuery.trim().toLowerCase()))
		: conversations;

	onMount(async () => {
		try {
			const res = await fetch('/api/config');
			if (res.ok) {
				const data = await res.json();
				if (data.orgName) orgName = data.orgName;
			}
		} catch (e) {
			// ใช้ชื่อเริ่มต้นถ้าดึงค่าไม่สำเร็จ
		}

		voiceSupported = typeof window !== 'undefined' && ('SpeechRecognition' in window || 'webkitSpeechRecognition' in window);

		await Promise.all([refreshConversations(), loadUsage(), loadModels()]);
		if (conversations.length > 0) {
			await selectConversation(conversations[0].id);
		}
	});

	function toggleSidebar() {
		if (typeof window !== 'undefined' && window.innerWidth < 768) {
			sidebarOpen = !sidebarOpen;
		} else {
			sidebarCollapsed = !sidebarCollapsed;
		}
	}

	async function loadModels() {
		try {
			models = await fetchModels();
		} catch (e) {
			models = [];
		}
	}

	/** ดาวน์โหลดโมเดลให้อัตโนมัติถ้ายังไม่มี แล้วอัปเดตสถานะ installed ในรายการ */
	async function ensureModelReady(modelId, busyLabel) {
		const entry = models.find((m) => m.id === modelId);
		if (!entry || entry.installed) return true;

		modelBusy = true;
		modelBusyLabel = busyLabel;
		try {
			await pullModel(modelId);
			await loadModels();
			return true;
		} catch (e) {
			alert(`${$t('chat.modelPrepareFailed')}: ${e.message}`);
			return false;
		} finally {
			modelBusy = false;
			modelBusyLabel = '';
		}
	}

	async function handleModelChange() {
		const entry = models.find((m) => m.id === selectedModel);
		if (entry) await ensureModelReady(selectedModel, `${$t('chat.modelDownloading')} ${entry.label}...`);
	}

	async function refreshConversations() {
		try {
			conversations = await listConversations();
		} catch (e) {
			conversations = [];
		}
	}

	async function loadUsage() {
		try {
			usage = await fetchUsage();
		} catch (e) {
			// เพิกเฉย จะลองใหม่รอบถัดไป
		}
	}

	function usagePercent(value, limit) {
		if (limit === null || limit === undefined || limit <= 0) return 0;
		return Math.min(100, Math.max(0, (value / limit) * 100));
	}

	function usageBarColor(percent) {
		if (percent >= 90) return 'bg-red-500';
		if (percent >= 70) return 'bg-amber-500';
		return 'bg-gradient-to-r from-violet-500 to-fuchsia-500';
	}

	async function scrollToBottom() {
		await tick();
		if (chatEl) chatEl.scrollTop = chatEl.scrollHeight;
	}

	async function selectConversation(id) {
		currentConversationId = id;
		sidebarOpen = false;
		try {
			const msgs = await getMessages(id);
			messages = msgs.map((m) => ({ role: m.role, content: m.content }));
		} catch (e) {
			messages = [];
		}
		await scrollToBottom();
	}

	function startNewConversation() {
		currentConversationId = null;
		messages = [];
		sidebarOpen = false;
	}

	async function removeConversation(id, event) {
		event.stopPropagation();
		if (!confirm($t('chat.deleteConfirm'))) return;
		try {
			await deleteConversation(id);
			conversations = conversations.filter((c) => c.id !== id);
			if (currentConversationId === id) startNewConversation();
		} catch (e) {
			// เพิกเฉยหากลบไม่สำเร็จ ผู้ใช้สามารถลองใหม่ได้
		}
	}

	function stopGenerating() {
		abortController?.abort();
	}

	async function sendMessage() {
		const text = input.trim();
		if ((!text && !attachedImage && !attachedFile) || loading || streaming || modelBusy) return;

		const outgoingImage = attachedImage;
		const outgoingFile = attachedFile;

		let displayText = text;
		if (outgoingFile) displayText += (displayText ? '\n\n' : '') + `📄 ${outgoingFile.name}`;
		if (outgoingImage) displayText += (displayText ? '\n\n' : '') + `🖼️ ${outgoingImage.name}`;

		messages = [...messages, { role: 'user', content: displayText }];
		input = '';
		attachedImage = null;
		attachedFile = null;
		loading = true;
		streaming = true;
		await scrollToBottom();

		let assistantIndex = -1;
		let finalContent = '';
		abortController = new AbortController();

		function ensureAssistantMessage() {
			if (assistantIndex === -1) {
				messages = [...messages, { role: 'assistant', content: '' }];
				assistantIndex = messages.length - 1;
				loading = false;
			}
		}

		try {
			await streamChat(
				{
					message: text,
					conversationId: currentConversationId,
					mode,
					model: selectedModel,
					images: outgoingImage ? [outgoingImage.dataUrl] : [],
					attachmentText: outgoingFile ? outgoingFile.text : '',
					attachmentName: outgoingFile ? outgoingFile.name : ''
				},
				{
					onConversationId: (id) => {
						currentConversationId = id;
					},
					onDelta: (delta) => {
						ensureAssistantMessage();
						finalContent += delta;
						messages[assistantIndex] = { ...messages[assistantIndex], content: finalContent };
						scrollToBottom();
					},
					onError: (msg) => {
						ensureAssistantMessage();
						messages[assistantIndex] = { ...messages[assistantIndex], content: finalContent || msg, error: true };
					},
					onDone: () => {
						refreshConversations();
						loadUsage();
						if (autoSpeak && finalContent) speak(finalContent);
					}
				},
				abortController.signal
			);
		} catch (e) {
			if (e.name === 'AbortError') {
				// ผู้ใช้กดหยุดเอง — เก็บคำตอบบางส่วนที่มีอยู่ไว้ (backend บันทึกให้แล้ว) ไม่ต้องแจ้ง error
				refreshConversations();
				loadUsage();
			} else {
				messages = [
					...messages,
					{ role: 'assistant', content: e.message || 'ไม่สามารถเชื่อมต่อกับ AI ได้', error: true }
				];
			}
		} finally {
			loading = false;
			streaming = false;
			abortController = null;
			await scrollToBottom();
		}
	}

	async function handleLogout() {
		try {
			await logout();
		} catch (e) {
			// เพิกเฉย แล้วพากลับหน้า login เสมอ
		}
		user.set(null);
		goto('/login');
	}

	function speak(text) {
		if (typeof window === 'undefined' || !('speechSynthesis' in window)) return;
		window.speechSynthesis.cancel();
		const utterance = new SpeechSynthesisUtterance(text);
		utterance.lang = 'th-TH';
		window.speechSynthesis.speak(utterance);
	}

	function parseContent(content) {
		const parts = [];
		const regex = /```(\w+)?\n?([\s\S]*?)```/g;
		let lastIndex = 0;
		let match;

		while ((match = regex.exec(content)) !== null) {
			if (match.index > lastIndex) {
				parts.push({ type: 'text', value: content.slice(lastIndex, match.index) });
			}
			parts.push({ type: 'code', lang: match[1] || '', value: match[2] });
			lastIndex = regex.lastIndex;
		}
		if (lastIndex < content.length) {
			parts.push({ type: 'text', value: content.slice(lastIndex) });
		}

		return parts.length ? parts : [{ type: 'text', value: content }];
	}

	async function copyCode(text) {
		try {
			await navigator.clipboard.writeText(text);
		} catch (e) {
			// เพิกเฉยหากคัดลอกไม่สำเร็จ (เช่น เบราว์เซอร์ไม่รองรับ)
		}
	}
</script>

<div class="flex h-screen bg-ink-950">
	{#if sidebarOpen}
		<div class="fixed inset-0 z-30 bg-black/60 md:hidden" on:click={() => (sidebarOpen = false)}></div>
	{/if}

	<aside
		class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col overflow-hidden border-r border-ink-700 bg-ink-900/95 backdrop-blur-xl transition-all duration-200 md:relative
		{sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
		{sidebarCollapsed ? 'md:w-0 md:border-r-0 md:translate-x-0' : 'md:w-72 md:translate-x-0'}"
	>
		<div class="flex shrink-0 flex-col gap-2 border-b border-ink-700 p-3">
			<button
				on:click={startNewConversation}
				class="flex w-full items-center gap-2 rounded-lg border border-ink-700 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-ink-800"
			>
				<span class="text-base leading-none">✎</span>
				{$t('chat.newConversation')}
			</button>
			<div class="relative">
				<span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">🔍</span>
				<input
					bind:value={searchQuery}
					placeholder={$t('chat.searchChats')}
					class="w-full rounded-lg border border-ink-700 bg-ink-800 py-2 pl-9 pr-3 text-sm text-slate-200 placeholder-slate-500 transition focus:border-violet-500 focus:outline-none"
				/>
			</div>
		</div>

		<div class="flex-1 overflow-y-auto p-2">
			{#if conversations.length === 0}
				<p class="p-3 text-center text-sm text-slate-500">{$t('chat.noHistory')}</p>
			{:else if filteredConversations.length === 0}
				<p class="p-3 text-center text-sm text-slate-500">{$t('chat.noSearchResults')}</p>
			{/if}
			{#each filteredConversations as c (c.id)}
				<button
					on:click={() => selectConversation(c.id)}
					class="group mb-1 flex w-full items-center justify-between gap-2 rounded-lg border-l-2 px-3 py-2 text-left text-sm transition
					{c.id === currentConversationId
						? 'border-violet-500 bg-violet-500/10 text-violet-300'
						: 'border-transparent text-slate-400 hover:bg-ink-800 hover:text-slate-200'}"
				>
					<span class="truncate">{c.title}</span>
					<span
						role="button"
						tabindex="0"
						on:click={(e) => removeConversation(c.id, e)}
						title={$t('delete')}
						class="shrink-0 rounded px-1.5 py-0.5 text-xs text-slate-500 opacity-0 hover:bg-red-500/10 hover:text-red-400 group-hover:opacity-100"
					>
						🗑
					</span>
				</button>
			{/each}
		</div>

		{#if usage}
			<div class="shrink-0 border-t border-ink-700 p-4">
				<p class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500">{$t('chat.usageToday')}</p>

				<div class="mb-3">
					<div class="mb-1.5 flex items-center justify-between text-xs">
						<span class="text-slate-400">{$t('chat.questions')}</span>
						<span class="font-medium text-slate-300">
							{usage.requests}{usage.requestLimit !== null ? ` / ${usage.requestLimit}` : ` / ${$t('unlimited')}`}
						</span>
					</div>
					{#if usage.requestLimit !== null}
						<div class="h-1.5 overflow-hidden rounded-full bg-ink-800">
							<div
								class="h-full rounded-full transition-all {usageBarColor(usagePercent(usage.requests, usage.requestLimit))}"
								style="width:{usagePercent(usage.requests, usage.requestLimit)}%"
							></div>
						</div>
					{/if}
				</div>

				<div>
					<div class="mb-1.5 flex items-center justify-between text-xs">
						<span class="text-slate-400">{$t('chat.tokens')}</span>
						<span class="font-medium text-slate-300">
							{usage.tokens.toLocaleString()}{usage.tokenLimit !== null ? ` / ${usage.tokenLimit.toLocaleString()}` : ` / ${$t('unlimited')}`}
						</span>
					</div>
					{#if usage.tokenLimit !== null}
						<div class="h-1.5 overflow-hidden rounded-full bg-ink-800">
							<div
								class="h-full rounded-full transition-all {usageBarColor(usagePercent(usage.tokens, usage.tokenLimit))}"
								style="width:{usagePercent(usage.tokens, usage.tokenLimit)}%"
							></div>
						</div>
					{/if}
				</div>
			</div>
		{/if}
	</aside>

	<div class="flex flex-1 flex-col">
		<header class="flex items-center gap-3 border-b border-ink-700 bg-ink-900/80 px-4 py-4 backdrop-blur-xl sm:px-6">
			<button
				on:click={toggleSidebar}
				title={$t('chat.toggleSidebar')}
				class="rounded-lg p-2 text-slate-400 hover:bg-ink-800"
				aria-label="toggle sidebar"
			>
				☰
			</button>

			<Logo size={40} />
			<div class="min-w-0 flex-1">
				<h1 class="truncate text-xl font-bold leading-tight">
					<span class="brand-text">FAONEX</span><span class="text-violet-400">.AI</span>
				</h1>
				<p class="truncate text-xs text-slate-500">{orgName}</p>
			</div>

			<div class="hidden items-center gap-1 rounded-xl border border-ink-700 bg-ink-800 p-1 sm:flex">
				<button
					on:click={() => (mode = 'chat')}
					class="rounded-lg px-3 py-1.5 text-xs font-medium transition {mode === 'chat'
						? 'bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white shadow-glow-sm'
						: 'text-slate-400 hover:text-slate-200'}"
				>
					{$t('chat.modeChat')}
				</button>
				<button
					on:click={() => (mode = 'code')}
					class="rounded-lg px-3 py-1.5 text-xs font-medium transition {mode === 'code'
						? 'bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white shadow-glow-sm'
						: 'text-slate-400 hover:text-slate-200'}"
				>
					{$t('chat.modeCode')}
				</button>
			</div>

			{#if mode === 'chat'}
				<select
					bind:value={selectedModel}
					on:change={handleModelChange}
					disabled={modelBusy}
					class="hidden max-w-[9rem] shrink-0 rounded-lg border border-ink-700 bg-ink-800 px-2 py-1.5 text-xs text-slate-300 disabled:opacity-50 sm:block"
				>
					{#each models.filter((m) => m.kind === 'chat') as m}
						<option value={m.id}>{m.label} ({m.vendor}){m.installed ? '' : ' ⬇'}</option>
					{/each}
				</select>
			{/if}

			{#if modelBusy}
				<span class="hidden items-center gap-1.5 text-xs text-violet-300 sm:flex">
					<span class="h-2 w-2 animate-pulse rounded-full bg-violet-400"></span>
					{modelBusyLabel}
				</span>
			{/if}

			<button
				on:click={() => (autoSpeak = !autoSpeak)}
				title={$t('chat.autoSpeakTitle')}
				class="rounded-lg p-2 text-sm transition {autoSpeak
					? 'bg-violet-500/15 text-violet-300 shadow-glow-sm'
					: 'text-slate-500 hover:bg-ink-800 hover:text-slate-300'}"
			>
				🔊
			</button>

			<Settings />

			{#if $user?.role === 'admin'}
				<a
					href="/admin"
					title={$t('chat.adminPanel')}
					class="hidden shrink-0 items-center gap-1.5 rounded-lg border border-violet-500/30 bg-violet-500/10 px-3 py-1.5 text-xs font-semibold text-violet-300 transition hover:bg-violet-500/20 hover:shadow-glow-sm sm:flex"
				>
					<span>🛡</span>
					<span>{$t('chat.adminPanel')}</span>
				</a>
			{/if}

			<div class="hidden items-center gap-2 sm:flex">
				<span class="text-sm text-slate-400">{$user?.displayName ?? ''}</span>
				<button on:click={handleLogout} class="text-sm font-medium text-slate-500 hover:text-red-400">
					{$t('chat.logout')}
				</button>
			</div>
		</header>

		<div class="flex items-center gap-1 border-b border-ink-700 bg-ink-900/80 px-4 py-2 sm:hidden">
			<button
				on:click={() => (mode = 'chat')}
				class="flex-1 rounded-lg px-3 py-1.5 text-xs font-medium transition {mode === 'chat'
					? 'bg-violet-500/15 text-violet-300'
					: 'text-slate-500'}"
			>
				{$t('chat.modeChat')}
			</button>
			<button
				on:click={() => (mode = 'code')}
				class="flex-1 rounded-lg px-3 py-1.5 text-xs font-medium transition {mode === 'code'
					? 'bg-violet-500/15 text-violet-300'
					: 'text-slate-500'}"
			>
				{$t('chat.modeCode')}
			</button>
			{#if $user?.role === 'admin'}
				<a
					href="/admin"
					class="flex shrink-0 items-center gap-1 rounded-lg border border-violet-500/30 bg-violet-500/10 px-2 py-1.5 text-xs font-semibold text-violet-300"
				>
					<span>🛡</span><span>{$t('chat.adminPanelShort')}</span>
				</a>
			{/if}
			<button on:click={handleLogout} class="px-2 text-xs font-medium text-slate-500 hover:text-red-400">
				{$t('chat.logoutShort')}
			</button>
		</div>

		{#if messages.length === 0}
			<!-- ยังไม่มีข้อความ: เอาช่องแชทมาไว้กึ่งกลางจอ เหมือนหน้าเริ่มต้นของ ChatGPT -->
			<div class="flex flex-1 flex-col items-center justify-center overflow-y-auto px-4 py-6 sm:px-8">
				<div class="w-full max-w-3xl">
					<div class="mb-6 text-center">
						<Logo size={48} />
						<p class="mt-3 text-lg text-slate-300">{$t('chat.greeting')}</p>
					</div>

					{#if modelBusy}
						<div class="mb-2 flex items-center gap-2 rounded-lg border border-violet-500/30 bg-violet-500/10 px-3 py-2 text-xs text-violet-300 sm:hidden">
							<span class="h-2 w-2 animate-pulse rounded-full bg-violet-400"></span>
							{modelBusyLabel}
						</div>
					{/if}

					<ChatInputBar
						bind:input
						bind:attachedImage
						bind:attachedFile
						{mode}
						{modelBusy}
						{loading}
						{streaming}
						{voiceSupported}
						on:send={sendMessage}
						on:stop={stopGenerating}
						on:imageAttached={() => ensureModelReady('vision', $t('chat.visionDownloading'))}
					/>
				</div>
			</div>
		{:else}
			<main bind:this={chatEl} class="flex-1 overflow-y-auto px-4 py-6 sm:px-8">
				<div class="mx-auto flex max-w-3xl flex-col gap-6">
					{#each messages as m}
						{#if m.role === 'user'}
							<div class="flex justify-end">
								<div class="max-w-[85%] whitespace-pre-wrap rounded-2xl bg-gradient-to-br from-violet-600 to-fuchsia-600 px-4 py-2.5 text-sm text-white shadow-glow-sm">
									{m.content}
								</div>
							</div>
						{:else}
							<div class="group flex gap-3">
								<div
									class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white shadow-glow-sm
									{m.error ? 'bg-red-500' : 'bg-gradient-to-br from-violet-600 to-fuchsia-600'}"
								>
									AI
								</div>
								<div class="min-w-0 flex-1 text-sm leading-relaxed {m.error ? 'text-red-300' : 'text-slate-200'}">
									{#each parseContent(m.content) as part}
										{#if part.type === 'code'}
											<div class="my-2 overflow-hidden rounded-lg border border-white/10 bg-black/60">
												<div class="flex items-center justify-between bg-white/5 px-3 py-1 text-xs text-violet-300">
													<span>{part.lang || 'code'}</span>
													<button on:click={() => copyCode(part.value)} class="text-slate-400 hover:text-white">{$t('chat.copyCode')}</button>
												</div>
												<pre class="overflow-x-auto p-3 text-xs leading-relaxed text-slate-200"><code>{part.value}</code></pre>
											</div>
										{:else if part.value.trim() !== ''}
											<p class="whitespace-pre-wrap">{part.value}</p>
										{/if}
									{/each}

									{#if !m.error && m.content}
										<button
											on:click={() => speak(m.content)}
											title={$t('chat.readAloud')}
											class="mt-1 hidden items-center gap-1 rounded-lg px-1.5 py-0.5 text-xs text-slate-500 hover:bg-ink-800 hover:text-violet-300 group-hover:inline-flex"
										>
											🔊 {$t('chat.readAloud')}
										</button>
									{/if}
								</div>
							</div>
						{/if}
					{/each}

					{#if loading}
						<div class="flex gap-3">
							<div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-600 to-fuchsia-600 text-[10px] font-bold text-white shadow-glow-sm">
								AI
							</div>
							<div class="flex items-center gap-1 pt-2.5">
								<span class="h-2 w-2 animate-bounce rounded-full bg-violet-400 [animation-delay:-0.3s]"></span>
								<span class="h-2 w-2 animate-bounce rounded-full bg-violet-400 [animation-delay:-0.15s]"></span>
								<span class="h-2 w-2 animate-bounce rounded-full bg-violet-400"></span>
							</div>
						</div>
					{/if}
				</div>
			</main>

			<footer class="border-t border-ink-700 bg-ink-900/80 px-4 py-4 backdrop-blur-xl sm:px-8">
				<div class="mx-auto max-w-3xl">
					{#if modelBusy}
						<div class="mb-2 flex items-center gap-2 rounded-lg border border-violet-500/30 bg-violet-500/10 px-3 py-2 text-xs text-violet-300 sm:hidden">
							<span class="h-2 w-2 animate-pulse rounded-full bg-violet-400"></span>
							{modelBusyLabel}
						</div>
					{/if}

					<ChatInputBar
						bind:input
						bind:attachedImage
						bind:attachedFile
						{mode}
						{modelBusy}
						{loading}
						{streaming}
						{voiceSupported}
						on:send={sendMessage}
						on:stop={stopGenerating}
						on:imageAttached={() => ensureModelReady('vision', $t('chat.visionDownloading'))}
					/>
				</div>
			</footer>
		{/if}
	</div>
</div>
