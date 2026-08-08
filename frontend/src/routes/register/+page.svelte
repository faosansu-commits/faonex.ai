<script>
	import { register } from '$lib/api.js';
	import { user } from '$lib/stores.js';
	import { goto } from '$app/navigation';
	import Logo from '$lib/Logo.svelte';

	let username = '';
	let displayName = '';
	let password = '';
	let confirmPassword = '';
	let error = '';
	let loading = false;

	async function handleSubmit() {
		error = '';
		if (password !== confirmPassword) {
			error = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
			return;
		}
		loading = true;
		try {
			const u = await register(username, password, displayName);
			user.set(u);
			goto('/');
		} catch (e) {
			error = e.message;
		} finally {
			loading = false;
		}
	}
</script>

<div class="relative flex h-screen items-center justify-center overflow-hidden bg-ink-950 px-4">
	<div class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-600/25 blur-[120px]"></div>
	<div class="pointer-events-none absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-fuchsia-600/20 blur-[120px]"></div>

	<form
		on:submit|preventDefault={handleSubmit}
		class="relative w-full max-w-sm rounded-2xl border border-white/10 bg-ink-900/80 p-8 shadow-glow-lg backdrop-blur-xl"
	>
		<div class="mb-6 flex items-center gap-3">
			<Logo size={44} />
			<div>
				<h1 class="text-2xl font-bold leading-none">
					<span class="brand-text">FAONEX</span><span class="text-violet-400">.AI</span>
				</h1>
				<p class="mt-1 text-xs text-slate-500">สมัครสมาชิกเพื่อเริ่มใช้งาน</p>
			</div>
		</div>

		{#if error}
			<div class="mb-4 rounded-lg border border-red-500/30 bg-red-950/40 px-3 py-2 text-sm text-red-300">
				{error}
			</div>
		{/if}

		<label class="mb-3 block text-sm">
			<span class="mb-1 block text-slate-400">ชื่อผู้ใช้</span>
			<input
				bind:value={username}
				required
				autocomplete="username"
				class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
			/>
		</label>
		<label class="mb-3 block text-sm">
			<span class="mb-1 block text-slate-400">ชื่อที่แสดง (ไม่บังคับ)</span>
			<input
				bind:value={displayName}
				class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
			/>
		</label>
		<label class="mb-3 block text-sm">
			<span class="mb-1 block text-slate-400">รหัสผ่าน (อย่างน้อย 6 ตัวอักษร)</span>
			<input
				type="password"
				bind:value={password}
				required
				autocomplete="new-password"
				class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
			/>
		</label>
		<label class="mb-6 block text-sm">
			<span class="mb-1 block text-slate-400">ยืนยันรหัสผ่าน</span>
			<input
				type="password"
				bind:value={confirmPassword}
				required
				autocomplete="new-password"
				class="w-full rounded-lg border border-ink-600 bg-ink-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
			/>
		</label>

		<button
			type="submit"
			disabled={loading}
			class="w-full rounded-lg bg-gradient-to-r from-violet-600 to-fuchsia-600 px-4 py-2.5 text-sm font-medium text-white shadow-glow transition hover:shadow-glow-lg disabled:opacity-50"
		>
			{loading ? 'กำลังสมัครสมาชิก...' : 'สมัครสมาชิก'}
		</button>

		<p class="mt-4 text-center text-sm text-slate-500">
			มีบัญชีอยู่แล้ว? <a href="/login" class="font-medium text-violet-400 hover:text-violet-300">เข้าสู่ระบบ</a>
		</p>
	</form>
</div>
