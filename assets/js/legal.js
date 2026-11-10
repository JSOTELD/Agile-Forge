/**
 * AgileForge v2 - Legal & Compliance Module (The Legal Shield)
 * Auditoría jurídica del workspace, validación de normativas y generador de documentos.
 */

import { appData, apiFetch, escapeHtml } from './state.js';
import { showToast } from './ui.js';

let cachedLegalAudit = null;

export async function runLegalWorkspaceAudit(force = false) {
    if (cachedLegalAudit && !force) {
        renderLegalPillars(cachedLegalAudit);
        return;
    }

    const btnText = document.getElementById('legal-audit-btn-text');
    if (btnText) btnText.textContent = 'Auditando...';

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'audit_workspace_legal' })
        });
        const data = await res.json();
        if (data.success) {
            cachedLegalAudit = data;
            renderLegalPillars(data);
            showToast('Blindaje Legal', `Auditoría completada para ${data.jurisdiction?.country_name || 'jurisdicción activa'}`, 'success');
        } else {
            showToast('Auditoría no realizada', data.error || 'No se pudo auditar la carpeta del proyecto', 'error');
        }
    } catch (err) {
        showToast('Error', 'Fallo al ejecutar auditoría legal', 'error');
    } finally {
        if (btnText) btnText.textContent = 'Auditar Workspace Legal';
    }
}

export function renderLegalPillars(data) {
    const container = document.getElementById('legal-pillars-container');
    const scoreEl = document.getElementById('legal-overall-score');
    if (!container || !data) return;

    if (scoreEl) scoreEl.textContent = `${data.overall_compliance_score || 95}%`;

    const pillars = data.compliance_pillars || [];
    container.innerHTML = pillars.map(p => {
        const statusColors = {
            compliant: 'border-emerald-500/30 bg-emerald-950/20 text-emerald-400',
            partial:   'border-amber-500/30 bg-amber-950/20 text-amber-400',
            missing:   'border-red-500/30 bg-red-950/20 text-red-400'
        };
        const statusLabels = {
            compliant: '✓ Conforme',
            partial:   '⚠️ Parcial',
            missing:   '✕ Ausente'
        };

        return `
            <div class="glass-card rounded-2xl p-4 border border-slate-800 space-y-2.5 hover:border-amber-500/30 transition">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-white text-xs">${escapeHtml(p.pillar)}</span>
                    <span class="text-[9px] font-mono px-2 py-0.5 rounded-full border ${statusColors[p.status] || statusColors.partial}">
                        ${statusLabels[p.status] || 'Evaluado'}
                    </span>
                </div>
                <p class="text-[11px] text-slate-300 leading-snug">${escapeHtml(p.description || '')}</p>
                <div class="pt-2 border-t border-slate-900 text-[10px] text-slate-400 font-mono">
                    Norma: <span class="text-amber-300/90">${escapeHtml(p.applicable_law || '')}</span>
                </div>
            </div>
        `;
    }).join('');
}

export async function handleGenerateLegalDoc(saveToDisk = false) {
    const docType = document.getElementById('legal-gen-type')?.value || 'terminos';
    // Vacío = el servidor usa el nombre del proyecto activo
    const bizName = document.getElementById('legal-gen-name')?.value?.trim() || '';

    showToast('Generando Documento', 'Redactando con cláusulas según jurisdicción activa...', 'info');

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'generate_legal_doc',
                doc_type: docType,
                site_name: bizName,
                business_name: bizName,
                save_to_workspace: saveToDisk
            })
        });
        const data = await res.json();
        if (data.success) {
            const previewContainer = document.getElementById('legal-preview-container');
            const previewCode = document.getElementById('legal-preview-code');
            const previewFilename = document.getElementById('legal-preview-filename');
            const previewStatus = document.getElementById('legal-preview-status');

            if (previewContainer) previewContainer.classList.remove('hidden');
            if (previewCode) previewCode.value = data.content || '';
            if (previewFilename) previewFilename.textContent = data.filename || 'legal.php';
            if (previewStatus) previewStatus.textContent = saveToDisk ? 'Guardado en disco' : 'Borrador en pantalla';

            if (saveToDisk) {
                showToast('Documento Guardado', `Guardado en ${data.saved_path || data.filename}`, 'success');
            } else {
                showToast('Vista Previa Lista', `Previsualizando ${data.filename}`, 'info');
            }
        } else {
            showToast('No se generó', data.error || 'No se pudo generar el documento legal', 'error');
        }
    } catch (err) {
        showToast('Error', 'No se pudo generar el documento legal', 'error');
    }
}

