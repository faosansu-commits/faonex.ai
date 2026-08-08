const BASE = '/api';

async function request(path, options = {}) {
	const res = await fetch(BASE + path, {
		headers: { 'Content-Type': 'application/json' },
		credentials: 'same-origin',
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

/**
 * Streams /chat as newline-delimited JSON events so replies render token-by-token
 * instead of waiting for the full answer. Each line is one of:
 *   { conversationId }  — sent immediately, before the model starts responding
 *   { delta }           — a text chunk to append to the growing reply
 *   { error }           — something failed mid-stream (HTTP status is already 200 by then)
 *   { done, promptTokens, completionTokens } — stream finished successfully
 */
export async function streamChat(payload, handlers, signal) {
	const res = await fetch(`${BASE}/chat`, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		credentials: 'same-origin',
		body: JSON.stringify(payload),
		signal
	});

	if (!res.ok || !res.body) {
		const data = await res.json().catch(() => ({}));
		throw new Error(data.error || 'เกิดข้อผิดพลาด');
	}

	const reader = res.body.getReader();
	const decoder = new TextDecoder();
	let buffer = '';

	while (true) {
		const { value, done: streamDone } = await reader.read();
		if (streamDone) break;
		buffer += decoder.decode(value, { stream: true });

		let newlineIndex;
		while ((newlineIndex = buffer.indexOf('\n')) !== -1) {
			const line = buffer.slice(0, newlineIndex).trim();
			buffer = buffer.slice(newlineIndex + 1);
			if (!line) continue;

			let event;
			try {
				event = JSON.parse(line);
			} catch (e) {
				continue;
			}

			if (event.error) {
				handlers.onError?.(event.error);
			} else if (event.delta) {
				handlers.onDelta?.(event.delta);
			} else if (event.done) {
				handlers.onDone?.(event);
			} else if (event.conversationId !== undefined) {
				handlers.onConversationId?.(event.conversationId);
			}
		}
	}
}

export function fetchUsage() {
	return request('/usage/me');
}

export function fetchModels() {
	return request('/models').then((d) => d.models);
}

export function pullModel(modelId) {
	return request('/models/pull', { method: 'POST', body: JSON.stringify({ modelId }) });
}

export function adminListUsers(q = '') {
	const qs = q ? `?q=${encodeURIComponent(q)}` : '';
	return request(`/admin/users${qs}`).then((d) => d.users);
}

export function adminCreateUser(payload) {
	return request('/admin/users', { method: 'POST', body: JSON.stringify(payload) }).then((d) => d.user);
}

export function adminUpdateUser(id, payload) {
	return request(`/admin/users/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
}

export function adminDeleteUser(id) {
	return request(`/admin/users/${id}`, { method: 'DELETE' });
}

export function adminListFlags() {
	return request('/admin/flags').then((d) => d.flags);
}

export function adminStats(period = 'day') {
	return request(`/admin/stats?period=${encodeURIComponent(period)}`);
}

export function adminSystem() {
	return request('/admin/system');
}

export function adminDatabase() {
	return request('/admin/database');
}

export function adminExportUsersUrl() {
	return `${BASE}/admin/users/export`;
}

export function adminExportUserPdfUrl(id) {
	return `${BASE}/admin/users/${id}/export`;
}

export async function adminImportUsers(file) {
	const formData = new FormData();
	formData.append('file', file);
	const res = await fetch(`${BASE}/admin/users/import`, {
		method: 'POST',
		credentials: 'same-origin',
		body: formData
	});
	const data = await res.json().catch(() => ({}));
	if (!res.ok) {
		throw new Error(data.error || 'เกิดข้อผิดพลาด');
	}
	return data;
}

export function adminListApiKeys() {
	return request('/admin/api-keys').then((d) => d.keys);
}

export function adminCreateApiKey(label) {
	return request('/admin/api-keys', { method: 'POST', body: JSON.stringify({ label }) }).then((d) => d.key);
}

export function adminRevokeApiKey(id) {
	return request(`/admin/api-keys/${id}`, { method: 'DELETE' });
}

export function adminListKnowledgeTopics() {
	return request('/admin/knowledge/topics').then((d) => d.topics);
}

export function adminCreateKnowledgeTopic(payload) {
	return request('/admin/knowledge/topics', { method: 'POST', body: JSON.stringify(payload) }).then((d) => d.topic);
}

export function adminUpdateKnowledgeTopic(id, payload) {
	return request(`/admin/knowledge/topics/${id}`, { method: 'PUT', body: JSON.stringify(payload) }).then((d) => d.topic);
}

export function adminDeleteKnowledgeTopic(id) {
	return request(`/admin/knowledge/topics/${id}`, { method: 'DELETE' });
}

export function adminListKnowledgeDocuments(topicId) {
	return request(`/admin/knowledge/topics/${topicId}/documents`).then((d) => d.documents);
}

export async function adminUploadKnowledgeDocument(topicId, file) {
	const formData = new FormData();
	formData.append('file', file);
	const res = await fetch(`${BASE}/admin/knowledge/topics/${topicId}/documents`, {
		method: 'POST',
		credentials: 'same-origin',
		body: formData
	});
	const data = await res.json().catch(() => ({}));
	if (!res.ok) {
		throw new Error(data.error || 'เกิดข้อผิดพลาด');
	}
	return data.document;
}

export function adminDeleteKnowledgeDocument(id) {
	return request(`/admin/knowledge/documents/${id}`, { method: 'DELETE' });
}

export function adminAddKnowledgeText(topicId, payload) {
	return request(`/admin/knowledge/topics/${topicId}/text`, { method: 'POST', body: JSON.stringify(payload) }).then(
		(d) => d.document
	);
}

export function adminKnowledgeTemplateUrl() {
	return `${BASE}/admin/knowledge/template`;
}
