import { createIcons, LayoutDashboard, ListChecks, Scale, CalendarDays, Clock3, UsersRound, Files, ContactRound, Mail, Settings2, Building2, ArrowUpRight, ShieldCheck, Menu, Plus, CircleArrowRight, ArrowRight, ChevronRight, Ellipsis, Leaf, Check, FileText, Search, Download, CircleCheck, Save, LogOut, X, LockKeyhole } from 'lucide';

const icons = { LayoutDashboard, ListChecks, Scale, CalendarDays, Clock3, UsersRound, Files, ContactRound, Mail, Settings2, Building2, ArrowUpRight, ShieldCheck, Menu, Plus, CircleArrowRight, ArrowRight, ChevronRight, Ellipsis, Leaf, Check, FileText, Search, Download, CircleCheck, Save, LogOut, X, LockKeyhole };
const renderIcons = () => createIcons({ icons });
renderIcons();
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const toastElement = document.querySelector('#toast');
let toastTimer;
function toast(message, error = false) {
    clearTimeout(toastTimer);
    toastElement.textContent = message;
    toastElement.classList.toggle('error', error);
    toastElement.hidden = false;
    toastTimer = setTimeout(() => { toastElement.hidden = true; }, 4500);
}
async function api(url, { method = 'GET', data, signal, headers = {} } = {}) {
    const multipart = data instanceof FormData;
    const response = await fetch(url, {
        method, signal, credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...(multipart ? {} : { 'Content-Type': 'application/json' }), ...headers },
        body: data === undefined ? undefined : (multipart ? data : JSON.stringify(data)),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401 && currentModule) setTimeout(() => { location.href = '/entrar'; }, 1500);
        const validation = result.errors ? Object.values(result.errors).flat().join(' ') : null;
        throw new Error(validation || result.message || 'Não foi possível salvar. Tente novamente.');
    }
    return result;
}
function element(tag, text, className) {
    const node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    if (className) node.className = className;
    return node;
}
const parseDate = value => new Date(value.replace(' ', 'T').replace(/([+-]\d{2})$/, '$1:00'));
const dateTime = value => parseDate(value).toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo', dateStyle: 'short', timeStyle: 'short' });
const currentModule = document.querySelector('[data-module]')?.dataset.module;

const mobileButton = document.querySelector('.mobile-menu');
const sidebar = document.querySelector('#sidebar');
const backdrop = document.querySelector('.sidebar-backdrop');
function closeSidebar() {
    sidebar?.classList.remove('is-open');
    if (backdrop) backdrop.hidden = true;
    mobileButton?.setAttribute('aria-expanded', 'false');
}
mobileButton?.addEventListener('click', () => {
    const open = sidebar.classList.toggle('is-open');
    backdrop.hidden = !open;
    mobileButton.setAttribute('aria-expanded', String(open));
});
backdrop?.addEventListener('click', closeSidebar);
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeSidebar(); });

const editorDialog = document.querySelector('#editor-dialog');
const editorForm = document.querySelector('#editor-form');
const detailDialog = document.querySelector('#detail-dialog');
const editorDefinitions = {
    task: ['Nova tarefa', 'Criar tarefa'], client: ['Adicionar cliente', 'Adicionar cliente'],
    case: ['Adicionar processo', 'Salvar processo'], appointment: ['Agendar compromisso', 'Agendar compromisso'],
    deadline: ['Registrar prazo', 'Registrar prazo'], document: ['Adicionar documento', 'Adicionar documento'],
};
let editorKind;
let editId;
let detailRequest;
function openEditor(kind, record = null) {
    if (!editorDefinitions[kind]) return;
    editorKind = kind;
    editId = record?.id;
    document.querySelector('#editor-title').textContent = record ? ({task:'Editar tarefa',client:'Editar cliente',case:'Editar processo',appointment:'Editar compromisso'}[kind]) : editorDefinitions[kind][0];
    document.querySelector('#editor-submit').textContent = record ? 'Salvar alterações' : editorDefinitions[kind][1];
    document.querySelector('#editor-fields').replaceChildren(document.querySelector(`#form-${kind}`).content.cloneNode(true));
    editorForm.querySelectorAll('[data-options]').forEach(select => {
        select.append(document.querySelector(`#${select.dataset.options}-options`).content.cloneNode(true));
        if (select.required) select.options[0].textContent = select.dataset.options === 'case' ? 'Selecione um processo' : 'Selecione um cliente';
    });
    if (record) Object.entries(record).forEach(([key, value]) => {
        const input = editorForm.elements.namedItem(key);
        if (!input) return;
        if (input.type === 'datetime-local' && value) {
            const date = parseDate(value);
            const parts = new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).format(date);
            input.value = parts.replace(' ', 'T');
        } else input.value = value ?? '';
    });
    editorForm.querySelector('.form-error').hidden = true;
    detailDialog?.close();
    editorDialog.showModal();
}
document.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
document.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('click', event => { if (event.target === dialog) {
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
} }));
editorForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const button = editorForm.querySelector('[type="submit"]');
    const original = button.textContent;
    const error = editorForm.querySelector('.form-error');
    button.disabled = true;
    button.textContent = 'Salvando…';
    error.hidden = true;
    try {
        const data = new FormData(editorForm);
        await api(editorKind === 'document' ? '/api/v1/documents' : (editId ? (editorKind === 'task' ? `/api/v1/tasks/${editId}` : `/api/v1/records/${editorKind}/${editId}`) : `/api/v1/records/${editorKind}`), { method: editId ? 'PATCH' : 'POST', data: editorKind === 'document' ? data : Object.fromEntries(data) });
        sessionStorage.setItem('facilitajud-notice', editorDefinitions[editorKind][1].replace(/^(Criar|Adicionar|Salvar|Agendar|Registrar)/, 'Registro salvo:'));
        location.reload();
    } catch (exception) { error.textContent = exception.message; error.hidden = false; }
    finally { button.disabled = false; button.textContent = original; }
});

async function openDetail(kind, id, editing = false) {
    detailRequest?.abort();
    detailRequest = new AbortController();
    const signal = detailRequest.signal;
    const content = document.querySelector('#detail-content');
    const actions = document.querySelector('#detail-actions');
    document.querySelector('#detail-title').textContent = 'Carregando detalhes';
    document.querySelector('#detail-kind').textContent = ({ task: 'Tarefa', case: 'Processo', client: 'Cliente', appointment: 'Compromisso', deadline: 'Prazo' })[kind];
    content.replaceChildren(...[1, 2, 3].map(() => element('div', '', 'skeleton')));
    actions.replaceChildren();
    detailDialog.showModal();
    try {
        const { record, related, can_edit } = await api(`/api/v1/records/${kind}/${id}`, { signal });
        if (signal.aborted || !detailDialog.open) return;
        if (editing && can_edit) { openEditor('task', record); return; }
        document.querySelector('#detail-title').textContent = record.title || record.name;
        content.replaceChildren();
        const list = element('dl', undefined, 'detail-grid');
        const addField = (label, value) => {
            if (value === undefined || value === null || value === '') return;
            const group = element('div');
            group.append(element('dt', label), element('dd', String(value)));
            list.append(group);
        };
        if (kind === 'case') {
            addField('Número', record.number || 'Consultivo / extrajudicial'); addField('Cliente', related.client);
            addField('Situação', record.status); addField('Vara / local', record.court); addField('Responsável', record.responsible);
        }
        if (kind === 'task') { addField('Prazo', dateTime(record.due_at)); addField('Prioridade', record.priority); addField('Situação', record.completed_at ? 'Concluída' : 'Pendente'); }
        if (kind === 'deadline') { addField('Vencimento', dateTime(record.due_at)); addField('Situação', record.completed_at ? 'Cumprido' : 'Pendente'); }
        if (kind === 'client') { addField('E-mail', record.email); addField('Telefone', record.phone); }
        if (kind === 'appointment') { addField('Data e horário', dateTime(record.starts_at)); addField('Tipo', record.kind); addField('Local', record.location); }
        content.append(list);
        if (record.context || record.notes) {
            const section = element('section', undefined, 'detail-section');
            section.append(element('h3', 'Contexto'), element('p', record.context || record.notes)); content.append(section);
        }
        if (record.legal_case_id) {
            const button = element('button', 'Abrir processo vinculado →', 'text-link');
            button.addEventListener('click', () => openDetail('case', record.legal_case_id)); content.append(button);
        }
        if (kind === 'case') {
            for (const [key, label] of [['tasks', 'Tarefas vinculadas'], ['deadlines', 'Prazos do processo'], ['documents', 'Documentos']]) {
                const section = element('section', undefined, 'detail-section'); section.append(element('h3', label));
                if (!related[key]?.length) section.append(element('p', 'Nenhum registro vinculado.'));
                for (const row of related[key] || []) {
                    if (key === 'documents') { const link = element('a', row.name); link.href = `/documentos/${row.id}/baixar`; section.append(link); }
                    else { const button = element('button', `${row.title} · ${row.completed_at ? 'Concluído' : dateTime(row.due_at)}`, 'related-link'); button.addEventListener('click', () => openDetail(key === 'tasks' ? 'task' : 'deadline', row.id)); section.append(button); }
                }
                content.append(section);
            }
        }
        if (kind === 'client' && related.cases?.length) {
            const section = element('section', undefined, 'detail-section'); section.append(element('h3','Processos vinculados'));
            related.cases.forEach(row => { const button=element('button',row.title,'related-link'); button.addEventListener('click',()=>openDetail('case',row.id)); section.append(button); }); content.append(section);
        }
        if (can_edit) {
            const button = element('button', ({task:'Editar tarefa',client:'Editar cliente',case:'Editar processo',appointment:'Editar compromisso'})[kind], 'button subtle');
            button.addEventListener('click', () => openEditor(kind, record)); actions.append(button);
        }
        const close = element('button', 'Fechar detalhes', 'button subtle'); close.addEventListener('click', () => detailDialog.close()); actions.append(close);
    } catch (exception) {
        if (exception.name === 'AbortError') return;
        document.querySelector('#detail-title').textContent = 'Não foi possível abrir';
        content.replaceChildren(element('p', exception.message, 'form-error'));
    }
}
detailDialog?.addEventListener('close', () => detailRequest?.abort());
document.addEventListener('click', event => {
    const create = event.target.closest('[data-create]'); if (create) openEditor(create.dataset.create);
    const detail = event.target.closest('[data-detail]'); if (detail) openDetail(detail.dataset.detail, detail.dataset.id);
    const edit = event.target.closest('[data-edit-task]'); if (edit) openDetail('task', edit.dataset.editTask, true);
});

const rows = () => [...document.querySelectorAll('.task-row')];
let taskFilter = currentModule === 'tarefas' ? 'pending' : 'home';
function filterTasks() {
    const query = (document.querySelector('[data-list-search]')?.value || '').toLocaleLowerCase('pt-BR').trim();
    let visible = 0;
    rows().forEach(row => {
        const done = row.classList.contains('is-complete');
        const filterMatches = taskFilter === 'all' || (taskFilter === 'completed' ? done : !done);
        row.hidden = !(filterMatches && row.dataset.search.includes(query));
        if (!row.hidden) visible++;
    });
    const empty = document.querySelector('#task-empty');
    if (empty) empty.hidden = visible > 0;
}
if (currentModule === 'tarefas') filterTasks();
document.querySelectorAll('[data-task-filter]').forEach(button => button.addEventListener('click', () => {
    taskFilter = button.dataset.taskFilter;
    document.querySelectorAll('[data-task-filter]').forEach(other => { other.classList.toggle('selected', other === button); other.setAttribute('aria-pressed', String(other === button)); });
    filterTasks();
}));
document.querySelector('#task-sort')?.addEventListener('change', event => {
    const rank = { Alta: 0, Média: 1, Baixa: 2 };
    const sorted = rows().sort((a, b) => event.target.value === 'priority' ? rank[a.dataset.priority] - rank[b.dataset.priority] || a.dataset.due.localeCompare(b.dataset.due) : a.dataset.due.localeCompare(b.dataset.due));
    document.querySelector('#task-list').append(...sorted);
});
document.querySelector('[data-list-search]')?.addEventListener('input', event => {
    if (currentModule === 'tarefas') { filterTasks(); return; }
    const query = event.target.value.toLocaleLowerCase('pt-BR').trim();
    const items = [...document.querySelectorAll('[data-search]')];
    items.forEach(row => { row.hidden = !row.dataset.search.includes(query); });
    document.querySelector('.search-empty').hidden = !items.length || items.some(row => !row.hidden);
});
function updateSummary(data) {
    document.querySelectorAll('[data-pending-count]').forEach(node => { node.textContent = data.pending; });
    document.querySelectorAll('[data-completed-count]').forEach(node => { node.textContent = data.completedCount; });
    const progress = document.querySelector('#task-progress'); if (progress) progress.value = data.completedCount;
    const hero = document.querySelector('#next-action');
    if (!hero) return;
    if (hero.dataset.nextKind === 'work') return;
    hero.dataset.nextId = data.next?.id || '';
    document.querySelector('#next-title').textContent = data.next?.title || 'Você está em dia com suas tarefas.';
    document.querySelector('#next-context').textContent = data.next?.context || 'Adicione uma nova tarefa para planejar o próximo passo.';
    const priority = document.querySelector('#next-priority'); priority.textContent = data.next ? `Prioridade ${data.next.priority}` : 'Tudo organizado';
    priority.className = `badge ${data.next?.priority === 'Alta' ? 'pink' : 'lavender'}`;
    document.querySelector('#next-due-label').textContent = data.next ? dateTime(data.next.due_at).split(',')[0] : 'Tudo em dia';
    document.querySelector('#next-due-time').textContent = data.next ? parseDate(data.next.due_at).toLocaleTimeString('pt-BR', { timeZone: 'America/Sao_Paulo', hour: '2-digit', minute: '2-digit' }) : '✓';
    document.querySelector('#open-next').textContent = data.next ? 'Abrir tarefa →' : 'Tudo em dia';
    document.querySelector('#open-next').disabled = !data.next;
}
document.querySelector('#next-action[data-next-kind=task] #open-next')?.addEventListener('click', () => {
    const id = document.querySelector('#next-action').dataset.nextId;
    if (id) openDetail('task', id); else openEditor('task');
});
document.querySelectorAll('.task-checkbox').forEach(checkbox => checkbox.addEventListener('change', async () => {
    const row = checkbox.closest('.task-row');
    const checked = checkbox.checked;
    const status = row.querySelector('.task-status');
    row.classList.toggle('is-complete', checked);
    checkbox.disabled = true;
    row.setAttribute('aria-busy', 'true');
    try {
        const data = await api(`/api/v1/tasks/${row.dataset.taskId}/completion`, { method: 'PATCH', data: { completed: checked } });
        status.textContent = checked ? 'Concluída' : row.dataset.priority;
        updateSummary(data);
        toast(data.message);
        const activityList = document.querySelector('#activity-list');
        if (activityList) {
            const activity = element('div', undefined, 'activity-row');
            const icon = element('span', checked ? '✓' : '↺', 'icon-tile green');
            const copy = element('span'); copy.append(element('p', `${checked ? 'Tarefa concluída' : 'Tarefa reaberta'}: ${row.querySelector('.task-title').textContent}`), element('small', 'Agora · neste escritório'));
            activity.append(icon, copy); activityList.prepend(activity);
            while (activityList.children.length > 4) activityList.lastElementChild.remove();
        }
    } catch (exception) { checkbox.checked = !checked; row.classList.toggle('is-complete', !checked); toast(exception.message, true); }
    finally { checkbox.disabled = false; row.removeAttribute('aria-busy'); }
}));
document.querySelectorAll('[data-complete-deadline]').forEach(button => button.addEventListener('click', async () => {
    button.disabled = true;
    try { await api(`/api/v1/deadlines/${button.dataset.completeDeadline}/completion`, { method: 'PATCH', data: { completed: true } }); sessionStorage.setItem('facilitajud-notice', 'Prazo marcado como cumprido.'); location.reload(); }
    catch (exception) { toast(exception.message, true); button.disabled = false; }
}));
document.querySelector('#settings-form')?.addEventListener('submit', async event => {
    event.preventDefault(); const form = event.target; const button = form.querySelector('[type="submit"]'); const error = form.querySelector('.form-error');
    button.disabled = true; error.hidden = true;
    try { await api('/api/v1/settings', { method: 'PATCH', data: { name: form.elements.name.value, display_name: form.elements.display_name.value, reminders: form.elements.reminders.checked } }); sessionStorage.setItem('facilitajud-notice', 'Alterações salvas.'); location.reload(); }
    catch (exception) { error.textContent = exception.message; error.hidden = false; }
    finally { button.disabled = false; }
});
const notice = sessionStorage.getItem('facilitajud-notice'); if (notice) { sessionStorage.removeItem('facilitajud-notice'); toast(notice); }

const neonUrl = document.querySelector('meta[name="neon-auth-url"]').content;
if (neonUrl) import('./neon-auth.js').then(({ setupNeonAuth }) => setupNeonAuth({ neonUrl, api, toast, currentModule })).catch(() => toast('Não foi possível carregar a autenticação. Recarregue a página.', true));

if (document.querySelector('#work-dialog')) {
    const dialog = document.querySelector('#work-dialog');
    const form = document.querySelector('#work-form');
    let active;
    document.querySelectorAll('[data-work-open], #next-action[data-next-kind=work] #open-next').forEach(button => button.addEventListener('click', async () => {
        try {
            const data = await api(`/api/v1/work/${button.dataset.workOpen || document.querySelector('#next-action').dataset.nextId}`); active = data.item;
            document.querySelector('#work-title').textContent = active.title;
            document.querySelector('#work-context').textContent = `${active.process_number || 'Processo não informado'} · ${active.context || ''}`;
            form.elements.status.value = active.status; form.elements.note.value = active.note || '';
            form.querySelector('.form-error').hidden = true;
            const history = document.querySelector('#work-history'); history.replaceChildren();
            for (const entry of data.history) { const row = element('div', entry.note); row.append(element('small', `${entry.actor} · ${entry.status} · ${dateTime(entry.created_at)}`)); history.append(row); }
            if (!data.history.length) history.append(element('p', 'Nenhum andamento registrado.', 'metadata'));
            dialog.showModal();
        } catch (error) { toast(error.message, true); }
    }));
    dialog.querySelector('.dialog-close').addEventListener('click', () => dialog.close());
    form.addEventListener('submit', async event => {
        event.preventDefault(); const button = form.querySelector('button'); button.disabled = true;
        try { await api(`/api/v1/work/${active.id}`, { method: 'PATCH', data: { status: form.elements.status.value, note: form.elements.note.value, version: active.version } }); location.reload(); }
        catch (error) { const node = form.querySelector('.form-error'); node.textContent = error.message; node.hidden = false; }
        finally { button.disabled = false; }
    });
    document.querySelector('#select-work')?.addEventListener('change', event => document.querySelectorAll('[data-work-select]').forEach(node => { node.checked = event.target.checked; }));
    document.querySelector('#assign-work')?.addEventListener('click', async () => {
        const ids = [...document.querySelectorAll('[data-work-select]:checked')].map(node => Number(node.value));
        if (!ids.length) { toast('Selecione as obrigações para atribuir.', true); return; }
        try { await api('/api/v1/work/assign', { method: 'POST', data: { ids, member_id: Number(document.querySelector('#assign-member').value) } }); location.reload(); }
        catch (error) { toast(error.message, true); }
    });
    const excelDialog = document.querySelector('#excel-dialog');
    if (excelDialog) {
        const excelForm = document.querySelector('#excel-form'); let preview;
        document.querySelector('#import-excel').addEventListener('click', () => excelDialog.showModal());
        excelDialog.querySelector('.dialog-close').addEventListener('click', () => excelDialog.close());
        const invalidate = () => { preview = null; document.querySelector('#excel-mapping').hidden = true; };
        ['file', 'sheet', 'header_row'].forEach(name => excelForm.elements[name].addEventListener('change', invalidate));
        function showError(error) { const node = excelForm.querySelector('.form-error'); node.textContent = error.message; node.hidden = false; }
        document.querySelector('#preview-excel').addEventListener('click', async event => {
            if (!excelForm.elements.file.files.length) { excelForm.elements.file.reportValidity(); return; }
            event.target.disabled = true; excelForm.querySelector('.form-error').hidden = true;
            try {
                preview = await api('/api/v1/work/preview', { method: 'POST', data: new FormData(excelForm) });
                const sheet = excelForm.elements.sheet; sheet.replaceChildren();
                preview.sheets.forEach(name => { const option = element('option', name); option.value = name; sheet.append(option); }); sheet.value = preview.sheet;
                document.querySelector('#excel-count').textContent = `${preview.total} linhas encontradas. Escolha as colunas e o responsável.`;
                document.querySelectorAll('[data-excel-column]').forEach(select => {
                    select.replaceChildren(element('option', 'Selecione')); select.firstChild.value = '';
                    Object.entries(preview.headers).forEach(([column,title]) => { const option = element('option', `${column} · ${title}`); option.value = column; select.append(option); });
                });
                document.querySelector('#excel-preview').textContent = preview.rows.map(row => `Linha ${row.row}: ${Object.values(row.values).join(' · ')}`).join('\n');
                document.querySelector('#excel-mapping').hidden = false;
            } catch (error) { showError(error); } finally { event.target.disabled = false; }
        });
        excelForm.addEventListener('submit', async event => {
            event.preventDefault(); if (!preview) return; const button = excelForm.querySelector('[type=submit], #excel-mapping button'); button.disabled = true;
            const mapping = {}; document.querySelectorAll('[data-excel-column]').forEach(node => { mapping[node.dataset.excelColumn] = node.value || null; });
            try { await api(`/api/v1/work/import/${preview.id}`, { method: 'POST', data: { mapping, sheet: preview.sheet, header_row: Number(excelForm.elements.header_row.value), assigned_member_id: Number(excelForm.elements.assigned_member_id.value) } }); location.reload(); }
            catch (error) { showError(error); } finally { button.disabled = false; }
        });
    }
}
document.querySelector('#invite-form')?.addEventListener('submit', async event => {
    event.preventDefault(); const form = event.target; const button = form.querySelector('button'); button.disabled = true;
    try { const data = await api('/api/v1/team/invite', { method: 'POST', data: teamData(form) }); sessionStorage.setItem('facilitajud-invite-url', data.url); sessionStorage.setItem('facilitajud-notice','Associado adicionado à equipe. Compartilhe o convite para ativar o acesso.'); location.reload(); }
    catch (error) { toast(error.message, true); } finally { button.disabled = false; }
});

function teamData(form) {
    const data = Object.fromEntries(new FormData(form)); data.category_id = data.category_id || null;
    data.permissions = !form.elements.custom_permissions || form.elements.custom_permissions.checked ? [...form.querySelectorAll('[name="permissions[]"]:checked')].map(input => input.value) : null;
    delete data['permissions[]']; delete data.custom_permissions; return data;
}
document.querySelectorAll('.team-form').forEach(form => form.addEventListener('submit', async event => {
    event.preventDefault(); const button = form.querySelector('button'); button.disabled = true;
    try { await api(form.dataset.teamEndpoint, {method: form.dataset.teamMethod, data: teamData(form)}); location.reload(); }
    catch(error) { toast(error.message, true); } finally { button.disabled = false; }
}));
document.querySelectorAll('[name="custom_permissions"]').forEach(input => {
    const update = () => { const grid = input.closest('form').querySelector('.permission-grid'); grid.hidden = !input.checked; grid.querySelectorAll('input').forEach(box => { box.disabled = !input.checked; }); };
    input.addEventListener('change', update); update();
});
document.querySelector('#copy-invite')?.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(document.querySelector('#invite-url').value); toast('Link copiado.'); }
    catch { document.querySelector('#invite-url').select(); toast('Selecione e copie o link do convite.'); }
});
const assignment = document.querySelector('#record-assignment');
if (assignment) {
    const options = [...assignment.elements.record.options].map(option => option.cloneNode(true));
    const update = () => { assignment.elements.record.replaceChildren(...options.filter(option => option.dataset.kind === assignment.elements.kind.value).map(option => option.cloneNode(true))); };
    assignment.elements.kind.addEventListener('change', update); update();
    assignment.addEventListener('submit', async event => {
        event.preventDefault(); const button = assignment.querySelector('button'); button.disabled = true;
        try { await api(`/api/v1/team/assign/${assignment.elements.kind.value}/${assignment.elements.record.value}`, {method: 'PATCH', data: {assigned_member_id: assignment.elements.assigned_member_id.value || null}}); toast('Responsável atualizado.'); }
        catch(error) { toast(error.message, true); } finally { button.disabled = false; }
    });
}

const loginBackground = document.querySelector('#login-background');
if (loginBackground) {
    import('./login-background.js').then(({ startLoginBackground }) => startLoginBackground(loginBackground)).catch(() => {});
}

const trialForm = document.querySelector('#trial-form');
trialForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const button = trialForm.querySelector('button');
    if (button.disabled) return;
    const originalLabel = button.innerHTML;
    const error = trialForm.querySelector('.form-error');
    const status = trialForm.querySelector('.auth-status');
    button.disabled = true;
    button.classList.add('is-loading');
    button.setAttribute('aria-busy', 'true');
    button.textContent = 'Abrindo o programa…';
    error.hidden = true;
    status.hidden = false;
    status.textContent = 'Preparando seu escritório.';
    try {
        const result = await api(trialForm.action, { method: 'POST', data: {} });
        location.href = result.redirect;
    } catch (exception) {
        error.textContent = exception.message;
        error.hidden = false;
        status.hidden = true;
        button.disabled = false;
        button.classList.remove('is-loading');
        button.removeAttribute('aria-busy');
        button.innerHTML = originalLabel;
    }
});

if (currentModule) import('./workspace-interactions.js').then(({ setupWorkspaceInteractions }) => setupWorkspaceInteractions({ api, toast, openEditor, dateTime })).catch(() => toast('Recarregue para carregar as interações.', true));
