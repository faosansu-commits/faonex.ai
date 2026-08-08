<script>
	import { onMount, tick } from 'svelte';
	import { goto } from '$app/navigation';
	import { user } from '$lib/stores.js';
	import Logo from '$lib/Logo.svelte';
	import {
		logout,
		listConversations,
		createConversation,
		getMessages,
		deleteConversation,
		sendChat
	} from '$lib/api.js';

	let orgName = 'ผู้ช่วย AI องค์กร';
	let conversations = [];
	let currentConversationId = null;
	let messages = []; // { role: 'user' | 'assistant', content: string, error?: boolean }
	let input = '';
	let loading = false;
	let mode = 'chat'; // 'chat' | 'code'
	let sidebarOpen = false;
	let autoSpeak = false;
	let recognizing = false;
	let voiceSupported = false;
	let chatEl;
	let recognition;

	onMount(async () => {
		try {
			const res = await fetch('/api/config');
			if (res.ok) {
				const data = await res.json();
				if (data.orgName) orgName = `ผู้ช่วย AI ${data.orgName}`;
			}
		} catch (e) {
			// ใช้ชื่อเริ่มต้นถ้าดึงค่าไม่สำเร็จ
		}

		voiceSupported = typeof window !== 'undefined' && ('SpeechRecognition' in window || 'webkitSpeechRecognition' in window);

		await refreshConversations();
		if (conversations.length > 0) {
			await selectConversation(conversations[0].id);
		}
	});

	async function refreshConversations() {
		try {
			conversations = await listConversations();
		} catch (e) {
			conversations = [];
		}
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
		if (!confirm('ต้องการลบบทสนทนานี้ใช่หรือไม่?')) return;
		try {
			await deleteConversation(id);
			conversations = conversations.filter((c) => c.id !== id);
			if (currentConversationId === id) startNewConversation();
		} catch (e) {
			// เพิกเฉยหากลบไม่สำเร็จ ผู้ใช้สามารถลองใหม่ได้
		}
	}

	async function sendMessage() {
		const text = input.trim();
		if (!text || loading) return;

		messages = [...messages, { role: 'user', content: text }];
		input = '';
		loading = true;
		await scrollToBottom();

		try {
			const data = await sendChat(text, currentConversationId, mode);
			currentConversationId = data.conversationId;
			messages = [...messages, { role: 'assistant', content: data.reply }];
			refreshConversations();
			if (autoSpeak) speak(data.reply);
		} catch (e) {
			messages = [
				...messages,
				{ role: 'assistant', content: e.message || 'ไม่สามารถเชื่อมต่อกับ AI ได้', error: true }
			];
		} finally {
			loading = false;
			await scrollToBottom();
		}
	}

	function handleKeydown(e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			sendMessage();
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

	function toggleVoice() {
		if (!voiceSupported) return;

		if (recognizing) {
			recognition?.stop();
			return;
		}

		const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
		recognition = new SpeechRecognition();
		recognition.lang = 'th-TH';
		recognition.interimResults = false;
		recognition.maxAlternatives = 1;

		recognition.onresult = (event) => {
			const transcript = event.results[0][0].transcript;
			input = input ? `${input} ${transcript}` : transcript;
		};
		recognition.onend = () => {
			recognizing = false;
		};
		recognition.onerror = () => {
			recognizing = false;
		};

		recognition.start();
		recognizing = true;
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
		class="fixed inset-y-0 left-0 z-40 flex w-72 transform flex-col border-r border-ink-700 bg-ink-900/95 backdrop-blur-xl transition-transform duration-200 md:relative md:translate-x-0
		{sidebarOpen ? 'translate-x-0' : '-translate-x-full'}"
	>
		<div class="border-b border-ink-700 p-4">
			<button
				on:click={startNewConversation}
				class="w-full rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 px-4 py-2.5 text-sm font-medium text-white shadow-glow-sm transition hover:shadow-glow"
			>
				+ บทสนทนาใหม่
			</button>
		</div>
		<div class="flex-1 overflow-y-auto p-2">
			{#if conversations.length === 0}
				<p class="p-3 text-center text-sm text-slate-500">ยังไม่มีประวัติการแชท</p>
			{/if}
			{#each conversations as c (c.id)}
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
						class="shrink-0 rounded px-1.5 py-0.5 text-xs text-slate-500 opacity-0 hover:bg-red-500/10 hover:text-red-400 group-hover:opacity-100"
					>
						ลบ
					</span>
				</button>
			{/each}
		</div>
	</aside>

	<div class="flex flex-1 flex-col">
		<header class="flex items-center gap-3 border-b border-ink-700 bg-ink-900/80 px-4 py-4 backdrop-blur-xl sm:px-6">
			<button
				on:click={() => (sidebarOpen = !sidebarOpen)}
				class="rounded-lg p-2 text-slate-400 hover:bg-ink-800 md:hidden"
				aria-label="เปิดประวัติการแชท"
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
					แชททั่วไป
				</button>
				<button
					on:click={() => (mode = 'code')}
					class="rounded-lg px-3 py-1.5 text-xs font-medium transition {mode === 'code'
						? 'bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white shadow-glow-sm'
						: 'text-slate-400 hover:text-slate-200'}"
				>
					เขียนโค้ด
				</button>
			</div>

			<button
				on:click={() => (autoSpeak = !autoSpeak)}
				title="อ่านคำตอบออกเสียงอัตโนมัติ"
				class="rounded-lg p-2 text-sm transition {autoSpeak
					? 'bg-violet-500/15 text-violet-300 shadow-glow-sm'
					: 'text-slate-500 hover:bg-ink-800 hover:text-slate-300'}"
			>
				🔊
			</button>

			<div class="hidden items-center gap-2 sm:flex">
				<span class="text-sm text-slate-400">{$user?.displayName ?? ''}</span>
				<button on:click={handleLogout} class="text-sm font-medium text-slate-500 hover:text-red-400">
					ออกจากระบบ
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
				แชททั่วไป
			</button>
			<button
				on:click={() => (mode = 'code')}
				class="flex-1 rounded-lg px-3 py-1.5 text-xs font-medium transition {mode === 'code'
					? 'bg-violet-500/15 text-violet-300'
					: 'text-slate-500'}"
			>
				เขียนโค้ด
			</button>
			<button on:click={handleLogout} class="px-2 text-xs font-medium text-slate-500 hover:text-red-400">
				ออก
			</button>
		</div>

		<main bind:this={chatEl} class="flex-1 overflow-y-auto px-4 py-6 sm:px-8">
			<div class="mx-auto flex max-w-3xl flex-col gap-4">
				{#if messages.length === 0}
					<div class="rounded-xl border border-dashed border-ink-600 bg-ink-900/60 p-6 text-center text-slate-500">
						สวัสดีครับ/ค่ะ พิมพ์หรือพูดคำถามที่ต้องการสอบถามได้เลย
					</div>
				{/if}

				{#each messages as m}
					<div class="flex {m.role === 'user' ? 'justify-end' : 'justify-start'}">
						<div
							class="group relative max-w-[85%] rounded-2xl px-4 py-2.5 text-sm transition
							{m.role === 'user'
								? 'bg-gradient-to-br from-violet-600 to-fuchsia-600 text-white shadow-glow-sm'
								: m.error
									? 'border border-red-500/30 bg-red-950/40 text-red-300'
									: 'border border-ink-700 bg-ink-900 text-slate-200 hover:border-violet-500/40'}"
						>
							{#each parseContent(m.content) as part}
								{#if part.type === 'code'}
									<div class="my-2 overflow-hidden rounded-lg border border-white/10 bg-black/60">
										<div class="flex items-center justify-between bg-white/5 px-3 py-1 text-xs text-violet-300">
											<span>{part.lang || 'code'}</span>
											<button on:click={() => copyCode(part.value)} class="text-slate-400 hover:text-white">คัดลอก</button>
										</div>
										<pre class="overflow-x-auto p-3 text-xs leading-relaxed text-slate-200"><code>{part.value}</code></pre>
									</div>
								{:else if part.value.trim() !== ''}
									<p class="whitespace-pre-wrap">{part.value}</p>
								{/if}
							{/each}

							{#if m.role === 'assistant' && !m.error}
								<button
									on:click={() => speak(m.content)}
									title="อ่านออกเสียง"
									class="absolute -bottom-2 -right-2 hidden rounded-full border border-ink-600 bg-ink-800 px-1.5 py-0.5 text-xs text-violet-300 shadow-glow-sm group-hover:block"
								>
									🔊
								</button>
							{/if}
						</div>
					</div>
				{/each}

				{#if loading}
					<div class="flex justify-start">
						<div class="flex items-center gap-1 rounded-2xl border border-ink-700 bg-ink-900 px-4 py-3">
							<span class="h-2 w-2 animate-bounce rounded-full bg-violet-400 [animation-delay:-0.3s]"></span>
							<span class="h-2 w-2 animate-bounce rounded-full bg-violet-400 [animation-delay:-0.15s]"></span>
							<span class="h-2 w-2 animate-bounce rounded-full bg-violet-400"></span>
						</div>
					</div>
				{/if}
			</div>
		</main>

		<footer class="border-t border-ink-700 bg-ink-900/80 px-4 py-4 backdrop-blur-xl sm:px-8">
			<div class="mx-auto flex max-w-3xl items-end gap-2">
				{#if voiceSupported}
					<button
						on:click={toggleVoice}
						title="พูดเพื่อพิมพ์ข้อความ"
						class="shrink-0 rounded-xl border px-3 py-2.5 text-sm transition {recognizing
							? 'animate-pulse border-red-500/40 bg-red-500/10 text-red-400 shadow-[0_0_15px_rgba(248,113,113,0.4)]'
							: 'border-ink-600 text-slate-400 hover:bg-ink-800 hover:text-slate-200'}"
					>
						🎤
					</button>
				{/if}
				<textarea
					bind:value={input}
					on:keydown={handleKeydown}
					rows="1"
					placeholder={mode === 'code' ? 'อธิบายโค้ดที่ต้องการให้เขียน...' : 'พิมพ์ข้อความ... (Enter เพื่อส่ง, Shift+Enter เพื่อขึ้นบรรทัดใหม่)'}
					class="flex-1 resize-none rounded-xl border border-ink-600 bg-ink-800 px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 transition focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
				></textarea>
				<button
					on:click={sendMessage}
					disabled={loading || !input.trim()}
					class="shrink-0 rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 px-5 py-2.5 text-sm font-medium text-white shadow-glow-sm transition hover:shadow-glow disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none"
				>
					ส่ง
				</button>
			</div>
		</footer>
	</div>
</div>
