<script>
	import { createEventDispatcher } from 'svelte';
	import { t } from '$lib/i18n.js';

	export let input = '';
	export let mode = 'chat';
	export let modelBusy = false;
	export let loading = false;
	export let streaming = false;
	export let attachedImage = null; // { name, dataUrl }
	export let attachedFile = null; // { name, text }
	export let voiceSupported = false;

	const dispatch = createEventDispatcher();

	let textareaEl;
	let fileInputEl;
	let attachMenuOpen = false;
	let recognizing = false;
	let recognition;

	$: sendDisabled = loading || modelBusy || (!input.trim() && !attachedImage && !attachedFile);
	// เมื่อข้อความถูกล้าง (หลังส่ง) ให้ช่องพิมพ์ยุบกลับขนาดเดิมอัตโนมัติ
	$: if (textareaEl && input === '') {
		textareaEl.style.height = 'auto';
	}

	function autoResizeTextarea(e) {
		const el = e.target;
		el.style.height = 'auto';
		el.style.height = Math.min(el.scrollHeight, 200) + 'px';
	}

	function handleKeydown(e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			if (!sendDisabled) dispatch('send');
		}
	}

	function readFileAsDataURL(file) {
		return new Promise((resolve, reject) => {
			const reader = new FileReader();
			reader.onload = () => resolve(reader.result);
			reader.onerror = () => reject(new Error('read failed'));
			reader.readAsDataURL(file);
		});
	}

	async function handleFileSelect(event) {
		const file = event.target.files?.[0];
		event.target.value = '';
		if (!file) return;

		attachedImage = null;
		attachedFile = null;

		if (file.type.startsWith('image/')) {
			if (file.size > 4 * 1024 * 1024) {
				alert($t('chat.imageTooLarge'));
				return;
			}
			const dataUrl = await readFileAsDataURL(file);
			attachedImage = { name: file.name, dataUrl };
			dispatch('imageAttached');
		} else {
			const text = await file.text();
			const truncated = text.length > 20000;
			attachedFile = {
				name: file.name,
				text: truncated ? `${text.slice(0, 20000)}\n...(truncated)` : text
			};
		}
	}

	function removeAttachment() {
		attachedImage = null;
		attachedFile = null;
	}

	function pickFile() {
		attachMenuOpen = false;
		fileInputEl.click();
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
</script>

<div>
	{#if attachedImage}
		<div class="mb-2 flex items-center gap-2 rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-xs text-slate-300">
			<img src={attachedImage.dataUrl} alt="" class="h-10 w-10 rounded object-cover" />
			<span class="flex-1 truncate">🖼️ {attachedImage.name}</span>
			<button on:click={removeAttachment} class="text-slate-500 hover:text-red-400">✕</button>
		</div>
	{/if}
	{#if attachedFile}
		<div class="mb-2 flex items-center gap-2 rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-xs text-slate-300">
			<span>📄</span>
			<span class="flex-1 truncate">{attachedFile.name}</span>
			<button on:click={removeAttachment} class="text-slate-500 hover:text-red-400">✕</button>
		</div>
	{/if}

	<div class="flex items-end gap-2">
		<input type="file" accept="image/*,.txt,.md,.csv" bind:this={fileInputEl} on:change={handleFileSelect} class="hidden" />

		<div class="relative">
			<button
				on:click={() => (attachMenuOpen = !attachMenuOpen)}
				title={$t('chat.attachTitle')}
				disabled={modelBusy}
				class="shrink-0 rounded-xl border px-3 py-2.5 text-base leading-none transition disabled:opacity-40
				{attachMenuOpen ? 'border-violet-500 bg-violet-500/10 text-violet-300' : 'border-ink-600 text-slate-400 hover:bg-ink-800 hover:text-slate-200'}"
			>
				➕
			</button>
			{#if attachMenuOpen}
				<div class="fixed inset-0 z-40" on:click={() => (attachMenuOpen = false)}></div>
				<div class="absolute bottom-full left-0 z-50 mb-2 w-60 overflow-hidden rounded-xl border border-ink-700 bg-ink-900 py-1 shadow-glow">
					<button
						on:click={pickFile}
						class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-slate-200 transition hover:bg-ink-800"
					>
						<span class="text-base">📎</span>
						<span>{$t('chat.attachMenuFiles')}</span>
					</button>
					<div class="my-1 border-t border-ink-800"></div>
					{#each [['🌐', $t('chat.searchWeb')], ['🔎', $t('chat.deepResearch')], ['🐙', $t('chat.connectGithub')], ['🎨', $t('chat.connectCanva')]] as [icon, label]}
						<div class="flex w-full cursor-not-allowed items-center gap-3 px-4 py-2.5 text-left text-sm text-slate-500">
							<span class="text-base opacity-60">{icon}</span>
							<span class="flex-1">{label}</span>
							<span class="rounded-full bg-ink-800 px-1.5 py-0.5 text-[10px] text-slate-500">{$t('chat.comingSoon')}</span>
						</div>
					{/each}
				</div>
			{/if}
		</div>

		{#if voiceSupported}
			<button
				on:click={toggleVoice}
				title={$t('chat.micTitle')}
				class="shrink-0 rounded-xl border px-3 py-2.5 text-sm transition {recognizing
					? 'animate-pulse border-red-500/40 bg-red-500/10 text-red-400 shadow-[0_0_15px_rgba(248,113,113,0.4)]'
					: 'border-ink-600 text-slate-400 hover:bg-ink-800 hover:text-slate-200'}"
			>
				🎤
			</button>
		{/if}
		<textarea
			bind:value={input}
			bind:this={textareaEl}
			on:keydown={handleKeydown}
			on:input={autoResizeTextarea}
			rows="1"
			placeholder={mode === 'code' ? $t('chat.placeholderCode') : $t('chat.placeholderChat')}
			class="max-h-[200px] flex-1 resize-none overflow-y-auto rounded-xl border border-ink-600 bg-ink-800 px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 transition focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
		></textarea>
		{#if streaming}
			<button
				on:click={() => dispatch('stop')}
				title={$t('chat.stopGenerating')}
				class="shrink-0 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 px-5 py-2.5 text-sm font-medium text-white shadow-glow-sm transition hover:shadow-glow"
			>
				⏹
			</button>
		{:else}
			<button
				on:click={() => dispatch('send')}
				disabled={sendDisabled}
				class="shrink-0 rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 px-5 py-2.5 text-sm font-medium text-white shadow-glow-sm transition hover:shadow-glow disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none"
			>
				{$t('chat.send')}
			</button>
		{/if}
	</div>
</div>
