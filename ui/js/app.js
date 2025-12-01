/**
 * Tayyebi Board - Main JavaScript
 */

const Board = {
    config: {
        apiBase: '/api',
        title: 'Tayyebi Board'
    },

    state: {
        swimlanes: {},
        grouped: {},
        lastModified: {},
        dragId: null,
        laneDragEl: null
    },

    // Initialize the board
    async init() {
        await this.loadData();
        this.bindEvents();
    },

    // API calls
    async api(endpoint, method = 'GET', data = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(this.config.apiBase + endpoint, options);
            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            return { error: error.message };
        }
    },

    // Load board data
    async loadData() {
        const data = await this.api('/tasks');
        if (data.error) {
            this.showNotice('Failed to load board data', true);
            return;
        }

        this.state.swimlanes = data.swimlanes || {};
        this.state.grouped = data.grouped || {};
        this.state.lastModified = data.lastModified || {};

        this.render();
    },

    // Render the entire board
    render() {
        this.renderHeader();
        this.renderCreateForm();
        this.renderSwimlanes();
    },

    // Render header
    renderHeader() {
        const container = document.getElementById('header-content');
        if (!container) return;

        const taskCount = Object.values(this.state.grouped)
            .flatMap(cols => Object.values(cols))
            .flat().length;

        container.innerHTML = `
            <div style="display:flex;align-items:center;gap:12px">
                <h1>${this.escapeHtml(this.config.title)}</h1>
                <div class="meta">
                    <span>${taskCount} items</span>
                    <span>Tasks: ${this.state.lastModified.tasks || '—'}</span>
                    <span>Lanes: ${this.state.lastModified.lanes || '—'}</span>
                </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <button class="btn" onclick="Board.openSettings()">Settings</button>
                <button class="btn" onclick="Board.refresh()">Refresh</button>
            </div>
        `;
    },

    // Render create task form
    renderCreateForm() {
        const container = document.getElementById('create-form');
        if (!container) return;

        const swimlaneOptions = Object.keys(this.state.swimlanes)
            .map(swl => `<option value="${this.escapeHtml(swl)}">${this.escapeHtml(swl)}</option>`)
            .join('');

        container.innerHTML = `
            <div class="label">Create task</div>
            <form class="form" onsubmit="Board.createTask(event)">
                <input type="text" name="title" placeholder="Title" required>
                <select name="swimlane" required onchange="Board.syncColumns(this)">
                    ${swimlaneOptions}
                </select>
                <select name="column" required></select>
                <input type="text" name="due" placeholder="Due (optional)">
                <textarea name="notes" placeholder="Notes (optional)"></textarea>
                <button class="btn" type="submit">Add</button>
            </form>
        `;

        // Initialize column dropdown
        const swimlaneSelect = container.querySelector('select[name="swimlane"]');
        if (swimlaneSelect) {
            this.syncColumns(swimlaneSelect);
        }
    },

    // Render all swimlanes
    renderSwimlanes() {
        const container = document.getElementById('board-content');
        if (!container) return;

        let html = '';
        for (const [swl, meta] of Object.entries(this.state.swimlanes)) {
            const cols = this.state.grouped[swl] || {};
            const stripeStyle = meta.color
                ? `background:linear-gradient(45deg, ${meta.color}, rgba(0,0,0,0.06));`
                : '';

            html += `
                <section class="swimlane" data-swl="${this.escapeHtml(swl)}">
                    <div class="swl-head">
                        <div class="stripe" aria-hidden="true" style="${stripeStyle}"></div>
                        <div>${this.escapeHtml(swl)}</div>
                    </div>
                    <div class="cols">
                        ${meta.cols.map(col => this.renderColumn(swl, col, cols[col] || [])).join('')}
                    </div>
                </section>
            `;
        }

        container.innerHTML = html;
    },

    // Render a single column
    renderColumn(swl, col, tasks) {
        // Find column color from raw lane data
        let colColor = '';
        // Column color would need to be stored in swimlanes structure
        // For now, we skip this as it requires API enhancement

        const colStripeStyle = colColor
            ? `background:linear-gradient(45deg, ${colColor}, rgba(0,0,0,0.06));`
            : '';

        const tasksHtml = tasks.length === 0
            ? '<div class="empty">No items</div>'
            : tasks.map(task => this.renderCard(task)).join('');

        return `
            <div class="col" data-col="${this.escapeHtml(col)}">
                <div class="col-head">
                    <div class="col-stripe" aria-hidden="true" style="${colStripeStyle}"></div>
                    <div>${this.escapeHtml(col)}</div>
                </div>
                <div class="col-body"
                     ondragover="event.preventDefault()"
                     ondrop="Board.dropOn(event, '${this.escapeHtml(swl)}', '${this.escapeHtml(col)}')">
                    ${tasksHtml}
                </div>
            </div>
        `;
    },

    // Render a task card
    renderCard(task) {
        const title = task.title || '(Untitled)';
        const dueHtml = task.due ? `<span>Due: ${this.escapeHtml(task.due)}</span>` : '';
        const notesHtml = task.notes
            ? `<div class="info" style="margin-top:6px">${this.escapeHtml(task.notes).replace(/\n/g, '<br>')}</div>`
            : '';

        return `
            <div class="card"
                 draggable="true"
                 ondragstart="Board.dragStart(event, '${task.id}')"
                 ondragend="Board.dragEnd(event)">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div class="title">${this.escapeHtml(title)}</div>
                    <div style="display:flex;gap:6px;align-items:center">
                        <button class="btn" onclick="Board.openTaskSettings('${task.id}')">Task settings</button>
                    </div>
                </div>
                <div class="info">
                    <span>ID: ${task.id}</span>
                    <span>Swimlane: ${this.escapeHtml(task.swimlane)}</span>
                    <span>Column: ${this.escapeHtml(task.column)}</span>
                    ${dueHtml}
                </div>
                ${notesHtml}
                <div class="actions">
                    <details>
                        <summary class="btn">Move</summary>
                        <form style="margin-top:6px" onsubmit="Board.moveTask(event, '${task.id}')">
                            <select name="swimlane" onchange="Board.syncColumns(this)">
                                ${this.getSwimlaneOptions(task.swimlane)}
                            </select>
                            <select name="column" data-current="${this.escapeHtml(task.column)}"></select>
                            <button class="btn" type="submit">Move</button>
                        </form>
                    </details>
                    <button class="btn" onclick="Board.deleteTask('${task.id}')">Delete</button>
                </div>
            </div>
        `;
    },

    // Get swimlane options HTML
    getSwimlaneOptions(selected = '') {
        return Object.keys(this.state.swimlanes)
            .map(swl => `<option value="${this.escapeHtml(swl)}" ${swl === selected ? 'selected' : ''}>${this.escapeHtml(swl)}</option>`)
            .join('');
    },

    // Sync column dropdown based on swimlane selection
    syncColumns(swimlaneSelect) {
        const form = swimlaneSelect.closest('form');
        const columnSelect = form?.querySelector('select[name="column"]');
        if (!columnSelect) return;

        const swimlane = swimlaneSelect.value;
        const cols = this.state.swimlanes[swimlane]?.cols || [];
        const current = columnSelect.getAttribute('data-current');

        columnSelect.innerHTML = cols
            .map(col => `<option value="${this.escapeHtml(col)}" ${col === current ? 'selected' : ''}>${this.escapeHtml(col)}</option>`)
            .join('');

        columnSelect.removeAttribute('data-current');
    },

    // Drag and drop
    dragStart(event, id) {
        this.state.dragId = id;
        event.target.classList.add('dragging');
    },

    dragEnd(event) {
        event.target.classList.remove('dragging');
    },

    async dropOn(event, swimlane, column) {
        event.preventDefault();
        if (!this.state.dragId) return;

        const result = await this.api(`/tasks/${this.state.dragId}/move`, 'PUT', {
            swimlane,
            column
        });

        this.state.dragId = null;

        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            await this.loadData();
        }
    },

    // Task operations
    async createTask(event) {
        event.preventDefault();
        const form = event.target;
        const data = {
            title: form.title.value,
            swimlane: form.swimlane.value,
            column: form.column.value,
            due: form.due.value,
            notes: form.notes.value
        };

        const result = await this.api('/tasks', 'POST', data);
        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            this.showNotice('Task created');
            form.reset();
            await this.loadData();
        }
    },

    async moveTask(event, id) {
        event.preventDefault();
        const form = event.target;
        const data = {
            swimlane: form.swimlane.value,
            column: form.column.value
        };

        const result = await this.api(`/tasks/${id}/move`, 'PUT', data);
        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            await this.loadData();
        }
    },

    async deleteTask(id) {
        if (!confirm('Delete this task?')) return;

        const result = await this.api(`/tasks/${id}`, 'DELETE');
        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            this.showNotice('Task deleted');
            await this.loadData();
        }
    },

    // Task settings modal
    async openTaskSettings(id) {
        const result = await this.api(`/tasks/${id}`);
        if (result.error || !result.task) {
            this.showNotice('Failed to load task', true);
            return;
        }

        const task = result.task;
        const modal = document.getElementById('taskModal');
        const form = document.getElementById('taskSettingsForm');

        form.querySelector('#task_id').value = task.id;
        form.querySelector('#task_title').value = task.title;
        form.querySelector('#task_notes').value = task.notes;
        form.querySelector('#task_due').value = task.due;

        const swimlaneSelect = form.querySelector('#task_swimlane');
        swimlaneSelect.innerHTML = this.getSwimlaneOptions(task.swimlane);

        const columnSelect = form.querySelector('#task_column');
        columnSelect.setAttribute('data-current', task.column);
        this.syncColumns(swimlaneSelect);

        modal.classList.add('active');
        setTimeout(() => form.querySelector('#task_title').focus(), 50);
    },

    closeTaskSettings() {
        document.getElementById('taskModal').classList.remove('active');
    },

    async saveTask(event) {
        event.preventDefault();
        const form = event.target;
        const id = form.querySelector('#task_id').value;

        const data = {
            title: form.querySelector('#task_title').value,
            notes: form.querySelector('#task_notes').value,
            due: form.querySelector('#task_due').value,
            swimlane: form.querySelector('#task_swimlane').value,
            column: form.querySelector('#task_column').value
        };

        const result = await this.api(`/tasks/${id}`, 'PUT', data);
        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            this.showNotice('Task updated');
            this.closeTaskSettings();
            await this.loadData();
        }
    },

    async deleteTaskFromModal() {
        const id = document.getElementById('task_id').value;
        if (!confirm('Delete this task?')) return;

        const result = await this.api(`/tasks/${id}`, 'DELETE');
        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            this.showNotice('Task deleted');
            this.closeTaskSettings();
            await this.loadData();
        }
    },

    // Settings modal
    async openSettings() {
        const modal = document.getElementById('settingsModal');
        this.renderSettingsContent();
        modal.classList.add('active');
    },

    closeSettings() {
        document.getElementById('settingsModal').classList.remove('active');
    },

    renderSettingsContent() {
        const lanesList = document.getElementById('lanesList');
        const swimlaneSelects = document.querySelectorAll('.settings-swimlane-select');

        // Render lanes list
        let lanesHtml = '';
        let idx = 0;
        for (const [swl, meta] of Object.entries(this.state.swimlanes)) {
            idx++;
            const colsHtml = meta.cols.map(col => `
                <span class="col-chip">
                    ${this.escapeHtml(col)}
                    <input class="color-input" type="color" data-swl="${this.escapeHtml(swl)}" data-col="${this.escapeHtml(col)}" value="#ffffff">
                </span>
            `).join('');

            lanesHtml += `
                <div class="lane-row" data-swl="${this.escapeHtml(swl)}" draggable="true"
                     ondragstart="Board.dragLaneStart(event)"
                     ondragover="event.preventDefault()"
                     ondrop="Board.dropLane(event)">
                    <div class="lane-handle">☰</div>
                    <div style="flex:1">
                        <strong>${this.escapeHtml(swl)}</strong>
                        <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
                            ${colsHtml}
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end">
                        <input class="color-input" type="color" data-swl="${this.escapeHtml(swl)}" value="${meta.color || '#ffffff'}">
                    </div>
                </div>
            `;
        }
        lanesList.innerHTML = lanesHtml;

        // Update swimlane selects
        const options = this.getSwimlaneOptions();
        swimlaneSelects.forEach(select => {
            select.innerHTML = options;
        });
    },

    // Swimlane drag and drop for reordering
    dragLaneStart(event) {
        this.state.laneDragEl = event.currentTarget;
        event.dataTransfer?.setData('text/plain', 'drag');
    },

    dropLane(event) {
        event.preventDefault();
        if (!this.state.laneDragEl) return;

        const target = event.currentTarget;
        const list = target.parentElement;
        if (!list) return;

        list.insertBefore(this.state.laneDragEl, target.nextSibling);
        this.state.laneDragEl = null;
    },

    // Settings operations
    async saveSettings() {
        const rows = document.querySelectorAll('#lanesList .lane-row');
        const meta = { swimlanes: {} };
        let order = 0;

        rows.forEach(row => {
            const swl = row.getAttribute('data-swl');
            order++;
            const swColorInput = row.querySelector(`input.color-input[data-swl="${swl}"]:not([data-col])`);
            const swColor = swColorInput ? swColorInput.value : '';
            const colInputs = row.querySelectorAll('input.color-input[data-col]');
            const cols = {};
            colInputs.forEach(ci => {
                const col = ci.getAttribute('data-col');
                cols[col] = ci.value;
            });
            meta.swimlanes[swl] = { color: swColor, order: order, columns: cols };
        });

        const result = await this.api('/lanes/meta', 'POST', meta);
        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            this.showNotice('Settings saved');
            await this.loadData();
        }
    },

    async addSwimlane(event) {
        event.preventDefault();
        const form = event.target;
        const data = {
            swimlane: form.swimlane.value,
            first_column: form.first_column.value
        };

        const result = await this.api('/lanes/swimlane', 'POST', data);
        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            this.showNotice('Swimlane added');
            form.reset();
            await this.loadData();
            this.renderSettingsContent();
        }
    },

    async renameSwimlane(event) {
        event.preventDefault();
        const form = event.target;
        const data = {
            old_swimlane: form.old_swimlane.value,
            new_swimlane: form.new_swimlane.value
        };

        const result = await this.api('/lanes/swimlane', 'PUT', data);
        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            this.showNotice('Swimlane renamed');
            form.reset();
            await this.loadData();
            this.renderSettingsContent();
        }
    },

    async deleteSwimlane(event) {
        event.preventDefault();
        if (!confirm('Delete swimlane and reassign tasks to fallback?')) return;

        const form = event.target;
        const data = {
            swimlane: form.swimlane.value,
            fallback_swimlane: form.fallback_swimlane.value
        };

        const result = await this.api('/lanes/swimlane', 'DELETE', data);
        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            this.showNotice('Swimlane deleted');
            await this.loadData();
            this.renderSettingsContent();
        }
    },

    async addColumn(event) {
        event.preventDefault();
        const form = event.target;
        const data = {
            swimlane: form.swimlane.value,
            column: form.column.value
        };

        const result = await this.api('/lanes/column', 'POST', data);
        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            this.showNotice('Column added');
            form.reset();
            await this.loadData();
            this.renderSettingsContent();
        }
    },

    async renameColumn(event) {
        event.preventDefault();
        const form = event.target;
        const data = {
            swimlane: form.swimlane.value,
            old_column: form.old_column.value,
            new_column: form.new_column.value
        };

        const result = await this.api('/lanes/column', 'PUT', data);
        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            this.showNotice('Column renamed');
            form.reset();
            await this.loadData();
            this.renderSettingsContent();
        }
    },

    async deleteColumn(event) {
        event.preventDefault();
        if (!confirm('Delete column and reassign tasks to fallback column?')) return;

        const form = event.target;
        const data = {
            swimlane: form.swimlane.value,
            column: form.column.value,
            fallback_column: form.fallback_column.value
        };

        const result = await this.api('/lanes/column', 'DELETE', data);
        if (result.error) {
            this.showNotice(result.error, true);
        } else {
            this.showNotice('Column deleted');
            await this.loadData();
            this.renderSettingsContent();
        }
    },

    // Utility functions
    refresh() {
        this.loadData();
    },

    showNotice(message, isError = false) {
        const container = document.getElementById('notice-container');
        if (!container) return;

        const notice = document.createElement('div');
        notice.className = `notice${isError ? ' error' : ''}`;
        notice.textContent = message;
        container.innerHTML = '';
        container.appendChild(notice);

        setTimeout(() => notice.remove(), 5000);
    },

    escapeHtml(str) {
        if (typeof str !== 'string') return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    bindEvents() {
        // Close modals on backdrop click
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) {
                    backdrop.classList.remove('active');
                }
            });
        });

        // Close modals on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => Board.init());
