const BASE = '/api';

async function request(path, options = {}) {
	const res = await fetch(BASE + path, {
		headers: { 'Content-Type': 'application/json' },
		...options
	});
	const data = await res.json().catch(() => ({}));
	if (!res.ok) {
		throw new Error(data.error || 'เกิดข้อผิดพลาด');
	}
	return data;
}

export function fetchMe() {
	return request('/auth/me').then((d) => d.user);
}

export function login(username, password) {
	return request('/auth/login', {
		method: 'POST',
		body: JSON.stringify({ username, password })
	}).then((d) => d.user);
}

export function register(username, password, displayName) {
	return request('/auth/register', {
		method: 'POST',
		body: JSON.stringify({ username, password, displayName })
	}).then((d) => d.user);
}

export function logout() {
	return request('/auth/logout', { method: 'POST' });
}

export function listConversations() {
	return request('/conversations').then((d) => d.conversations);
}

export function createConversation() {
	return request('/conversations', { method: 'POST' }).then((d) => d.id);
}

export function getMessages(conversationId) {
	return request(`/conversations/${conversationId}/messages`).then((d) => d.messages);
}

export function deleteConversation(conversationId) {
	return request(`/conversations/${conversationId}`, { method: 'DELETE' });
}

export function sendChat(message, conversationId, mode) {
	return request('/chat', {
		method: 'POST',
		body: JSON.stringify({ message, conversationId, mode })
	});
}
