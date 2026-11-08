import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export class AgileForgeClient {
  private apiUrl: string;
  private apiToken: string;
  private defaultClient: string;

  constructor() {
    this.apiUrl = process.env.AGILEFORGE_API_URL || 'http://localhost/Open-to-code/api';
    this.defaultClient = process.env.AGILEFORGE_CLIENT || 'claude-code';
    this.apiToken = process.env.AGILEFORGE_API_TOKEN || this.loadTokenFromEnv();
  }

  private loadTokenFromEnv(): string {
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
        } catch {
          // Continuar al siguiente candidato
        }
      }
    }
    return '';
  }

  private async request<T = any>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const url = endpoint.startsWith('http') ? endpoint : `${this.apiUrl.replace(/\/+$/, '')}/${endpoint.replace(/^\/+/, '')}`;
    const token = this.apiToken;

    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
      'X-AgileForge-Token': token,
      ...(options.headers as Record<string, string> || {})
    };

    if (options.body && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }

    try {
      const res = await fetch(url, { ...options, headers });
      const text = await res.text();
      let data: any;

      try {
        data = JSON.parse(text);
      } catch {
        throw new Error(`Respuesta no JSON del backend AgileForge (HTTP ${res.status}): ${text.substring(0, 200)}`);
      }

      if (!res.ok) {
        throw new Error(data.error || `HTTP ${res.status}: ${res.statusText}`);
      }

      return data as T;
    } catch (err: any) {
      throw new Error(`[AgileForge API Error] ${err.message}`);
    }
  }

  // --- Herramientas de Sprint ---

  async getSprintStatus() {
    return this.request('index.php/sprint/status');
  }

  // --- Herramientas de Tareas ---

  async listTasks(filters: { status?: string; role?: string; priority?: string; project?: string } = {}) {
    const query = new URLSearchParams();
    if (filters.status) query.append('status', filters.status);
    if (filters.role) query.append('role', filters.role);
    if (filters.priority) query.append('priority', filters.priority);
    if (filters.project) query.append('project', filters.project);

    const qs = query.toString();
    const endpoint = qs ? `index.php/tasks?${qs}` : 'index.php/tasks';
    return this.request(endpoint);
  }

  async getTask(taskId: string) {
    return this.request(`index.php/tasks/${encodeURIComponent(taskId)}`);
  }

  async claimTask(taskId: string, roleKey: string, client?: string) {
    return this.request(`index.php/tasks/${encodeURIComponent(taskId)}/claim`, {
      method: 'POST',
      body: JSON.stringify({
        role_key: roleKey,
        client: client || this.defaultClient
      })
    });
  }

  async updateTaskStatus(taskId: string, status: string, note: string, roleKey?: string, client?: string, humanApproved?: boolean) {
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

  async addComment(taskId: string, role: string, content: string, name?: string, avatar?: string) {
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

  async submitForReview(taskId: string, roleKey: string, filesChanged: string[], testsRun: string[], summary: string) {
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

  async reviewTask(taskId: string, decision: 'approve' | 'request_changes', checklist: string[], reviewerRole: string) {
    return this.request(`index.php/tasks/${encodeURIComponent(taskId)}/review`, {
      method: 'POST',
      body: JSON.stringify({
        decision,
        checklist,
        reviewer_role: reviewerRole
      })
    });
  }

  async createTask(data: {
    title: string;
    story_points?: number;
    assigned_to: string;
    priority?: 'alta' | 'media' | 'baja';
    user_story: string;
    dod: string;
    description?: string;
    skills?: string[];
  }) {
    return this.request('index.php/tasks', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  // --- Herramientas de Salas de Reunión ---

  async startMeeting(data: {
    meeting_type: 'brainstorming' | 'decision' | 'compliance' | 'daily';
    topic: string;
    participants: string[];
    title?: string;
  }) {
    return this.request('index.php/meetings', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async addMeetingTurn(meetingId: string, turn: {
    role_id: string;
    statement: string;
    name?: string;
    alias?: string;
    avatar?: string;
    ai_model?: string;
  }) {
    return this.request(`index.php/meetings/${encodeURIComponent(meetingId)}/turns`, {
      method: 'POST',
      body: JSON.stringify(turn)
    });
  }

  async closeMeeting(meetingId: string, data: {
    summary: string;
    resolution: string;
    action_items?: Array<{
      title: string;
      assigned_to: string;
      story_points: number;
      priority?: string;
      user_story?: string;
      dod?: string;
    }>;
  }) {
    return this.request(`index.php/meetings/${encodeURIComponent(meetingId)}/close`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  // --- Herramienta de Decisiones (ADR) ---

  async recordDecision(data: {
    title: string;
    context: string;
    decision: string;
    consequences: string;
    status?: 'proposed' | 'accepted' | 'rejected' | 'deprecated';
  }) {
    return this.request('index.php/decisions', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  // --- Roles y Brief de Sistema ---

  async getRoleBrief(roleId: string) {
    return this.request(`index.php/roles/${encodeURIComponent(roleId)}/brief`);
  }

  async listRoles() {
    return this.request('index.php/roles');
  }

  async getFullState() {
    return this.request('index.php/state');
  }
}

