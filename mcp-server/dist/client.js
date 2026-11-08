import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
export class AgileForgeClient {
    apiUrl;
    apiToken;
    defaultClient;
    constructor() {
        this.apiUrl = process.env.AGILEFORGE_API_URL || 'http://localhost/Open-to-code/api';
        this.defaultClient = process.env.AGILEFORGE_CLIENT || 'claude-code';
        this.apiToken = process.env.AGILEFORGE_API_TOKEN || this.loadTokenFromEnv();
    }
    loadTokenFromEnv() {
        // Buscar .env en el directorio actual o en la raíz del proyecto
        const candidates = [
            path.resolve(process.cwd(), '.env'),
            path.resolve(__dirname, '../../.env'),
            path.resolve(__dirname, '../.env')
        ];
        for (const p of candidates) {
            if (fs.existsSync(p)) {
                try {
                    const content = fs.readFileSync(p, 'utf8');
                    const match = content.match(/AGILEFORGE_API_TOKEN=([a-zA-Z0-9_-]+)/);
                    if (match && match[1]) {
                        return match[1].trim();
                    }
                }
                catch {
                    // Continuar al siguiente candidato
                }
            }
        }
        return '';
    }
    async request(endpoint, options = {}) {
        const url = endpoint.startsWith('http') ? endpoint : `${this.apiUrl.replace(/\/+$/, '')}/${endpoint.replace(/^\/+/, '')}`;
        const token = this.apiToken;
        const headers = {
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
            'X-AgileForge-Token': token,
            ...(options.headers || {})
        };
        if (options.body && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }
        try {
            const res = await fetch(url, { ...options, headers });
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            }
            catch {
                throw new Error(`Respuesta no JSON del backend AgileForge (HTTP ${res.status}): ${text.substring(0, 200)}`);
            }
            if (!res.ok) {
                throw new Error(data.error || `HTTP ${res.status}: ${res.statusText}`);
            }
            return data;
        }
        catch (err) {
            throw new Error(`[AgileForge API Error] ${err.message}`);
        }
    }
    // --- Herramientas de Sprint ---
    async getSprintStatus() {
        return this.request('index.php/sprint/status');
    }
    // --- Herramientas de Tareas ---
    async listTasks(filters = {}) {
        const query = new URLSearchParams();
        if (filters.status)
            query.append('status', filters.status);
        if (filters.role)
            query.append('role', filters.role);
        if (filters.priority)
            query.append('priority', filters.priority);
        if (filters.project)
            query.append('project', filters.project);
        const qs = query.toString();
        const endpoint = qs ? `index.php/tasks?${qs}` : 'index.php/tasks';
        return this.request(endpoint);
    }
    async getTask(taskId) {
        return this.request(`index.php/tasks/${encodeURIComponent(taskId)}`);
    }
    async claimTask(taskId, roleKey, client) {
        return this.request(`index.php/tasks/${encodeURIComponent(taskId)}/claim`, {
            method: 'POST',
            body: JSON.stringify({
                role_key: roleKey,
                client: client || this.defaultClient
            })
        });
    }
    async updateTaskStatus(taskId, status, note, roleKey, client, humanApproved) {
        return this.request(`index.php/tasks/${encodeURIComponent(taskId)}/status`, {
            method: 'POST',
            body: JSON.stringify({
                status,
                note,
                role: roleKey,
                client: client || this.defaultClient,
                human_approved: humanApproved
            })
        });
    }
    async addComment(taskId, role, content, name, avatar) {
        return this.request(`index.php/tasks/${encodeURIComponent(taskId)}/comments`, {
            method: 'POST',
            body: JSON.stringify({
                role,
                content,
                name: name || role,
                avatar: avatar || '💬'
            })
        });
    }
    async submitForReview(taskId, roleKey, filesChanged, testsRun, summary) {
        return this.request(`index.php/tasks/${encodeURIComponent(taskId)}/submit-review`, {
            method: 'POST',
            body: JSON.stringify({
                role_key: roleKey,
                evidence: {
                    files_changed: filesChanged,
                    tests_run: testsRun,
                    summary
                }
            })
        });
    }
    async reviewTask(taskId, decision, checklist, reviewerRole) {
        return this.request(`index.php/tasks/${encodeURIComponent(taskId)}/review`, {
            method: 'POST',
            body: JSON.stringify({
                decision,
                checklist,
                reviewer_role: reviewerRole
            })
        });
    }
    async createTask(data) {
        return this.request('index.php/tasks', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    // --- Herramientas de Salas de Reunión ---
    async startMeeting(data) {
        return this.request('index.php/meetings', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    async addMeetingTurn(meetingId, turn) {
        return this.request(`index.php/meetings/${encodeURIComponent(meetingId)}/turns`, {
            method: 'POST',
            body: JSON.stringify(turn)
        });
    }
    async closeMeeting(meetingId, data) {
        return this.request(`index.php/meetings/${encodeURIComponent(meetingId)}/close`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    // --- Herramienta de Decisiones (ADR) ---
    async recordDecision(data) {
        return this.request('index.php/decisions', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    // --- Roles y Brief de Sistema ---
    async getRoleBrief(roleId) {
        return this.request(`index.php/roles/${encodeURIComponent(roleId)}/brief`);
    }
    async listRoles() {
        return this.request('index.php/roles');
    }
    async getFullState() {
        return this.request('index.php/state');
    }
}
