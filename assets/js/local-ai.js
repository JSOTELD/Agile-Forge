/**
 * AgileForge v2 - IA Local (Ollama / Colibri)
 * Detecta si hay una IA local en esta PC y permite activarla o desactivarla con un clic.
 * Cuando está activada, el chat y las reuniones las responde el modelo local (sin enviar datos a la nube).
 */

import { appData, setAppData, apiFetch, escapeHtml } from './state.js';
import { showToast } from './ui.js';

let busy = false;

async function post(action, extra = {}) {
    const res = await apiFetch('api/tasks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...extra })
    });
    let data = {};
    try { data = await res.json(); } catch (e) { data = {}; }
    return { ok: res.ok && data.success !== false, data };
}

function storeState(localAi) {
    if (!localAi) return;
    setAppData({ local_ai: localAi });
    window.appData = appData;
}

export function getLocalAi() {
    return appData.local_ai || {};
}

const CHAT_RUNTIMES = ['ollama', 'colibri'];

/** Todos los modelos con los que se puede chatear: [{runtime, runtimeName, icon, ...modelo}] */
function allModels(state) {
    const out = [];
    (state.runtimes || []).filter(r => CHAT_RUNTIMES.includes(r.id)).forEach(rt => {
        (rt.models || []).forEach(m => out.push({ ...m, runtime: rt.id, runtimeName: rt.name, icon: rt.icon }));
    });
    // Colibri apagado pero AgileForge tiene permiso para encenderlo: sus modelos siguen disponibles
    if (!out.some(m => m.runtime === 'colibri')) {
        (state.colibri_offline_models || []).forEach(m => out.push({ ...(typeof m === 'string' ? { id: m } : m), runtime: 'colibri', runtimeName: 'Colibri', icon: '🐦', offline: true }));
    }
    return out;
}

/** ¿AgileForge puede encender Colibri por su cuenta? */
function canControlColibri(state) {
    return !!(state.colibri_control && state.colibri_control_supported);
}

let pollTimer = null;
/** Mientras Colibri enciende, revisa cada 5 s para actualizar el botón. */
function pollWhileStarting() {
    if (pollTimer) return;
    let rounds = 0;
    pollTimer = setInterval(async () => {
        rounds++;
        const s = await detectLocalAi({ silent: true });
        if (s.available || !s.colibri_starting || rounds > 55) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }, 5000);
}


/** Estado visual: 'none' | 'off' | 'on' | 'starting' | 'sleep' | 'down' | 'busy' */
function uiStatus(state) {
    if (busy) return 'busy';
    if (state.enabled && state.available) return 'on';
    if (state.enabled && state.runtime === 'colibri' && canControlColibri(state)) {
        return state.colibri_starting ? 'starting' : 'sleep';
    }
    if (state.enabled && !state.available) return 'down';
    if (state.detected) return 'off';
    return 'none';
}

export function renderLocalAiControls() {
    const state = getLocalAi();
    const status = uiStatus(state);
    const model = state.model || '';

    // --- Botón de la barra superior ---
    const btn = document.getElementById('local-ai-toggle');
    const label = document.getElementById('local-ai-label');
    const knob = document.getElementById('local-ai-knob');
    const track = document.getElementById('local-ai-track');
    if (btn && label && knob && track) {
        const texts = {
            busy: 'Un momento…',
            on: state.runtime === 'colibri' ? `${model} 🐦` : model,
            starting: 'Encendiendo 🐦…',
            sleep: 'En espera 🐦',
            off: 'Apagada',
            down: 'Sin respuesta',
            none: 'No detectada'
        };
        const titles = {
            busy: 'Consultando la IA local…',
            on: `IA local ACTIVADA con ${model} (${state.runtime === 'colibri' ? 'Colibri' : 'Ollama'}). El chat y las reuniones se responden en tu PC. Clic para desactivar.`,
            starting: 'Colibri se está encendiendo en tu PC (tarda 1–2 minutos). Clic para desactivar la IA local.',
            sleep: 'IA local activada con Colibri. Está apagado y AgileForge lo encenderá cuando lo necesite. Clic para desactivar.',
            off: `IA local disponible (${allModels(state).length} modelo(s)). Clic para activarla.`,
            down: `La IA local está activada pero ${state.runtime === 'colibri' ? 'Colibri' : 'Ollama'} no responde. Clic para buscarla de nuevo.`,
            none: 'No se encontró Ollama ni Colibri con modelos. Clic para buscar de nuevo.'
        };
        label.textContent = texts[status];
        btn.title = titles[status];
        btn.setAttribute('aria-checked', status === 'on' ? 'true' : 'false');
        btn.setAttribute('aria-label', 'IA local: ' + texts[status]);

        label.className = 'font-bold font-mono text-[11px] leading-tight ' + ({
            on: 'text-emerald-300', starting: 'text-sky-300', sleep: 'text-sky-300', off: 'text-slate-300', down: 'text-amber-300', none: 'text-slate-500', busy: 'text-slate-400'
        }[status]);
        btn.classList.toggle('border-emerald-500/50', status === 'on');
        btn.classList.toggle('border-amber-500/50', status === 'down');
        btn.classList.toggle('border-sky-500/50', status === 'starting' || status === 'sleep');
        btn.classList.toggle('opacity-70', status === 'none');

        const isOn = ['on', 'down', 'starting', 'sleep'].includes(status);
        track.className = 'relative inline-block w-8 h-[18px] rounded-full transition-colors ' +
            (status === 'on' ? 'bg-emerald-500' : status === 'down' ? 'bg-amber-500'
                : (status === 'starting' || status === 'sleep') ? 'bg-sky-500' : 'bg-slate-700') +
            (status === 'starting' ? ' animate-pulse' : '');
        knob.style.transform = isOn ? 'translateX(14px)' : 'translateX(0)';
    }

    // --- Sección dentro del modal "Motor IA" ---
    const statusEl = document.getElementById('local-ai-status-text');
    const select = document.getElementById('local-ai-model-select');
    const modalSwitch = document.getElementById('local-ai-modal-switch');
    if (statusEl) {
        const rts = state.runtimes || [];
        const parts = [];
        const ol = rts.find(r => r.id === 'ollama');
        const co = rts.find(r => r.id === 'colibri');
        const ver = v => (v ? ' ' + escapeHtml(v) : '');
        parts.push(ol ? `🦙 Ollama${ver(ol.version)}: ${ol.models.length} modelo(s)` : '🦙 Ollama: apagado');
        parts.push(co ? `🐦 Colibri${ver(co.version)}: ${co.models.length} modelo(s)`
            : state.colibri_starting ? '🐦 Colibri: encendiendo…'
            : canControlColibri(state) && (state.colibri_offline_models || []).length ? '🐦 Colibri: apagado (AgileForge lo enciende al usarlo)'
            : '🐦 Colibri: apagado');
        if (state.desktop_active) parts.push('ventana del escritorio abierta');
        const others = rts.filter(r => !CHAT_RUNTIMES.includes(r.id)).map(r => r.name);
        let txt = parts.join(' · ');
        if (others.length) txt += ` · También: ${others.map(escapeHtml).join(', ')} (solo detección)`;
        if (state.last_checked) {
            const d = new Date(state.last_checked);
            if (!isNaN(d)) txt += ` · revisado ${d.toLocaleTimeString()}`;
        }
        statusEl.innerHTML = txt;
    }
    if (select) {
        const models = allModels(state);
        const current = `${state.runtime || 'ollama'}::${model}`;
        select.innerHTML = models.length
            ? models.map(m => {
                const meta = [m.parameter_size, m.quantization, m.size_gb ? m.size_gb + ' GB' : '', m.context ? Math.round(m.context / 1024) + 'K ctx' : ''].filter(Boolean).join(' · ');
                const value = `${m.runtime}::${m.id}`;
                const off = m.offline ? ' (apagado — se enciende al usarlo)' : '';
                return `<option value="${escapeHtml(value)}" ${value === current ? 'selected' : ''}>${m.icon} ${escapeHtml(m.runtimeName)} · ${escapeHtml(m.id)}${meta ? ' — ' + escapeHtml(meta) : ''}${off}</option>`;
            }).join('')
            : '<option value="">Sin modelos: abre Ollama o inicia Colibri</option>';
        select.disabled = models.length === 0;
    }
    if (modalSwitch) {
        modalSwitch.checked = !!state.enabled;
        modalSwitch.disabled = !state.detected && !state.enabled;
    }
    const ctl = document.getElementById('local-ai-colibri-control');
    const ctlNote = document.getElementById('local-ai-colibri-control-note');
    if (ctl) {
        const supported = state.colibri_control_supported !== false;
        ctl.checked = !!state.colibri_control;
        ctl.disabled = !supported || busy;
        if (ctlNote) {
            ctlNote.textContent = !supported
                ? '⚠️ ' + (state.colibri_control_reason || 'No disponible en esta instalación.')
                : state.colibri_control
                    ? 'Mientras la IA local esté activada con Colibri, AgileForge lo mantiene encendido; al desactivarla lo apaga (salvo que tengas abierta la ventana "Colibri IA").'
                    : 'Sin este permiso AgileForge solo usa Colibri si tú lo abriste (acceso directo "Colibri IA" del escritorio).';
        }
    }

    // --- Badge "Motor IA" de la barra superior ---
    const headerModel = document.getElementById('header-ai-model');
    const headerIcon = document.getElementById('header-ai-icon');
    const engine = appData.ai_engine || {};
    if (headerModel && headerIcon) {
        if (status === 'on') {
            headerIcon.textContent = state.runtime === 'colibri' ? '🐦' : '🦙';
            headerModel.textContent = `${model} (local)`;
        } else {
            headerIcon.textContent = engine.icon || '🟣';
            headerModel.textContent = engine.active_model || 'claude-sonnet-5-5';
        }
    }
}

export async function detectLocalAi({ silent = false } = {}) {
    if (busy) return getLocalAi();
    busy = true;
    renderLocalAiControls();
    try {
        const { ok, data } = await post('detect_local_ai');
        if (ok) storeState(data.local_ai);
        if (!silent) {
            const s = getLocalAi();
            if (s.detected) {
                showToast('IA local encontrada', `${allModels(s).length} modelo(s) disponibles`, 'success', 2500);
            } else {
                showToast('Sin IA local', 'No se encontró Ollama ni Colibri. Abre Ollama o el acceso directo "Colibri IA".', 'warning');
            }
        }
    } catch (e) {
        if (!silent) showToast('Error', 'No se pudo consultar la IA local', 'error');
    } finally {
        busy = false;
        renderLocalAiControls();
    }
    if (getLocalAi().colibri_starting) pollWhileStarting();
    return getLocalAi();
}

export async function setLocalAiEnabled(enabled) {
    if (busy) return;
    let state = getLocalAi();
    if (enabled && !state.detected) {
        state = await detectLocalAi({ silent: true });
        if (!state.detected) {
            showToast('Sin IA local', 'No se encontró Ollama ni Colibri. Ábrelos o permite que AgileForge encienda Colibri.', 'warning');
            renderLocalAiControls();
            return;
        }
    }
    busy = true;
    renderLocalAiControls();
    try {
        const { ok, data } = await post('toggle_local_ai', { enabled, model: state.model || '', runtime: state.runtime || 'ollama' });
        if (data.local_ai) storeState(data.local_ai);
        if (ok) {
            const s = getLocalAi();
            if (enabled) {
                const starting = s.runtime === 'colibri' && !s.available;
                showToast('IA local activada', starting
                    ? `Encendiendo Colibri con ${s.model} en tu PC (1–2 minutos)…`
                    : `El chat y las reuniones ahora usan ${s.model} (${s.runtime === 'colibri' ? 'Colibri' : 'Ollama'}) en tu PC. Cargando el modelo…`, 'magic', 4500);
                if (starting) pollWhileStarting();
                warmUp();
            } else {
                const stopped = s.colibri_stopping_at && (Date.now() / 1000 - s.colibri_stopping_at) < 10;
                showToast('IA local desactivada', 'Se vuelve al motor configurado en "Motor IA".' + (stopped ? ' Colibri se está apagando para liberar memoria.' : ''), 'info', 3500);
                if (stopped) setTimeout(() => detectLocalAi({ silent: true }), 8000);
            }
        } else {
            showToast('No se pudo cambiar', data.error || 'Error al cambiar la IA local', 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo cambiar la IA local', 'error');
    } finally {
        busy = false;
        renderLocalAiControls();
    }
}

/**
 * Carga el modelo en memoria apenas se activa, para que el primer mensaje del chat no espere la carga
 * (la primera vez puede tardar alrededor de un minuto).
 */
function warmUp() {
    post('test_local_ai')
        .then(({ ok, data }) => {
            if (ok && data.result) {
                const r = data.result;
                showToast('Modelo local listo', `${r.model} responde a ${r.tokens_per_second || '?'} tokens/s`, 'success', 3000);
            } else if (data && data.error) {
                showToast('IA local', data.error, 'warning', 6000);
            }
            detectLocalAi({ silent: true });
        })
        .catch(() => {});
}

/** Clic en el botón de la barra superior. */
export async function toggleLocalAi() {
    const s = getLocalAi();
    const status = uiStatus(s);
    if (status === 'busy') return;
    if (status === 'starting' || status === 'sleep') {
        await setLocalAiEnabled(false);
        return;
    }
    if (status === 'none' || status === 'down') {
        const after = await detectLocalAi();
        if (status === 'down' && after.available) {
            showToast('IA local de nuevo disponible', `Usando ${after.model}`, 'success');
        }
        return;
    }
    await setLocalAiEnabled(!s.enabled);
}

export async function changeLocalAiModel(value) {
    if (!value) return;
    let runtime = 'ollama';
    let model = value;
    if (value.includes('::')) {
        [runtime, model] = value.split('::', 2);
    }
    busy = true;
    renderLocalAiControls();
    try {
        const { ok, data } = await post('update_local_ai', { model, runtime });
        if (data.local_ai) storeState(data.local_ai);
        showToast(ok ? 'Modelo local cambiado' : 'No se pudo cambiar', ok ? `${model} (${runtime === 'colibri' ? 'Colibri' : 'Ollama'})` : (data.error || ''), ok ? 'success' : 'error', 2500);
        if (ok && getLocalAi().enabled) warmUp();
    } finally {
        busy = false;
        renderLocalAiControls();
    }
}

/** Permiso para que AgileForge encienda y apague Colibri cuando la IA local lo necesite. */
export async function setColibriControl(allowed) {
    if (busy) return;
    busy = true;
    renderLocalAiControls();
    try {
        const { ok, data } = await post('update_local_ai_settings', { colibri_control: !!allowed });
        if (data.local_ai) storeState(data.local_ai);
        if (ok) {
            showToast(allowed ? 'Permiso concedido' : 'Permiso retirado', allowed
                ? 'AgileForge podrá encender Colibri cuando la IA local esté activada con él.'
                : 'AgileForge ya no encenderá ni apagará Colibri.', 'info', 3500);
        } else {
            showToast('No se pudo guardar', data.error || '', 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo guardar el permiso', 'error');
    } finally {
        busy = false;
        renderLocalAiControls();
    }
    if (getLocalAi().colibri_starting) pollWhileStarting();
}

export async function testLocalAi() {
    const out = document.getElementById('local-ai-test-output');
    const st = getLocalAi();
    if (out) out.textContent = st.runtime === 'colibri' && !st.available && canControlColibri(st)
        ? 'Encendiendo Colibri y probando… (1–2 minutos)'
        : 'Probando… (la primera respuesta tarda más mientras el modelo se carga)';
    if (st.runtime === 'colibri' && !st.available) setTimeout(() => detectLocalAi({ silent: true }), 3000);
    try {
        const { ok, data } = await post('test_local_ai');
        if (ok && data.result) {
            const r = data.result;
            const speed = r.tokens_per_second ? ` · ${r.tokens_per_second} tokens/s` : '';
            if (out) out.textContent = `✅ ${r.model}: "${r.text}" (${r.seconds}s${speed})`;
        } else if (out) {
            out.textContent = '⚠️ ' + (data.error || 'Sin respuesta');
        }
    } catch (e) {
        if (out) out.textContent = '⚠️ No se pudo probar la IA local';
    }
}

window.toggleLocalAi = toggleLocalAi;
window.detectLocalAi = detectLocalAi;
window.setLocalAiEnabled = setLocalAiEnabled;
window.changeLocalAiModel = changeLocalAiModel;
window.testLocalAi = testLocalAi;
window.setColibriControl = setColibriControl;
window.renderLocalAiControls = renderLocalAiControls;
