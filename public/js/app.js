// =========================================
// SubManager — Main Application Controller
// =========================================
import { api } from './api.js';
import { ui } from './ui.js';

window.app = {
    currentSection: 'dashboard',
    currentClientId: null,
    currentClientName: '',
    currentProjectId: null,
    currentProjectName: '',

    // ===================== INIT =====================
    async init() {
        // Auth Check
        const token = localStorage.getItem('submanager_token');
        if (!token) {
            this.showLogin();
        } else {
            try {
                const res = await api.auth.validate();
                this.onLoginSuccess(res.admin, token);
            } catch(e) {
                this.showLogin();
            }
        }

        // Login Form
        document.getElementById('login-form').onsubmit = (e) => {
            e.preventDefault();
            this.handleLogin();
        };

        // Logout
        document.getElementById('btn-logout').onclick = () => this.handleLogout();

        // Navigation
        window.addEventListener('hashchange', () => this.handleHash());

        // Sidebar toggle
        document.getElementById('sidebar-toggle').onclick = () => {
            document.getElementById('sidebar').classList.toggle('collapsed');
        };

        // Modal close
        document.getElementById('modal-close').onclick = () => ui.closeModal();
        document.getElementById('modal-overlay').onclick = (e) => {
            if (e.target === e.currentTarget) ui.closeModal();
        };

        // Button wiring
        document.getElementById('btn-add-client').onclick = () => this.showAddClientModal();
        document.getElementById('btn-add-project').onclick = () => this.showAddProjectModal();
        document.getElementById('btn-add-service').onclick = () => this.showAddServiceModal();

        // Client search
        document.getElementById('clients-search').oninput = (e) => this.filterClients(e.target.value);

        // Report controls
        document.getElementById('btn-refresh-reports').onclick = () => this.loadReporting();
        document.getElementById('report-expiry-days').onchange = () => this.loadReporting();
        document.getElementById('report-util-threshold').onchange = () => this.loadReporting();
    },

    showLogin() {
        document.getElementById('login-overlay').style.display = 'flex';
        document.getElementById('app-container').style.display = 'none';
    },

    async handleLogin() {
        const username = document.getElementById('login-username').value;
        const password = document.getElementById('login-password').value;
        const btn = document.getElementById('btn-login');
        const error = document.getElementById('login-error');

        btn.disabled = true;
        btn.querySelector('span').textContent = 'Authenticating...';
        btn.querySelector('.spinner-small').style.display = 'block';
        error.style.display = 'none';

        try {
            const res = await api.auth.login({ username, password });
            this.onLoginSuccess(res.admin, res.token);
        } catch(e) {
            error.textContent = e.message;
            error.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.querySelector('span').textContent = 'Sign In';
            btn.querySelector('.spinner-small').style.display = 'none';
        }
    },

    onLoginSuccess(admin, token) {
        localStorage.setItem('submanager_token', token);
        document.getElementById('display-admin-name').textContent = admin.name || admin.username;
        document.getElementById('login-overlay').style.display = 'none';
        document.getElementById('app-container').style.display = 'flex';
        this.handleHash();
    },

    handleLogout() {
        localStorage.removeItem('submanager_token');
        window.location.reload();
    },

    // ===================== ROUTING =====================
    handleHash() {
        const hash = (window.location.hash || '#dashboard').replace('#', '');
        const parts = hash.split('/');
        const section = parts[0] || 'dashboard';
        const id = parts[1] || null;
        this.showSection(section, id, false);
    },

    showSection(section, id = null, updateHash = true) {
        if (updateHash) {
            window.location.hash = id ? `${section}/${id}` : section;
            return;
        }

        // Hide all sections, show target
        document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
        const el = document.getElementById(`section-${section}`);
        if (el) el.classList.add('active');

        // Update nav active state
        document.querySelectorAll('.nav-link').forEach(a => a.classList.remove('active'));
        // Mapping sub-sections to their parent nav links
        const navMap = {
            'projects': 'clients',
            'services': 'clients',
            'activity': 'activity',
            'api-docs': 'api-docs',
            'reporting': 'reporting',
            'dashboard': 'dashboard',
            'clients': 'clients'
        };
        const navTarget = navMap[section] || 'dashboard';
        const navEl = document.querySelector(`.nav-link[data-section="${navTarget}"]`);
        if (navEl) navEl.classList.add('active');

        this.currentSection = section;

        // Load section data
        if (section === 'dashboard') this.loadDashboard();
        if (section === 'clients')   this.loadClients();
        if (section === 'projects' && id) this.loadProjects(id, false);
        if (section === 'services' && id) this.loadServices(id, false);
        if (section === 'reporting') this.loadReporting();
        if (section === 'activity')  this.loadActivityLog();
    },

    // ===================== DASHBOARD =====================
    async loadDashboard() {
        ui.setBreadcrumb([{ label: 'Dashboard', href: '#dashboard' }]);

        try {
            const [expiring, high, clients] = await Promise.all([
                api.reporting.expiring(30),
                api.reporting.highUtilization(90),
                api.clients.list()
            ]);

            // Count active from all clients (sum all services)
            let activeCount = 0;
            for (const client of clients) {
                try {
                    const projects = await api.projects.listByClient(client.id);
                    for (const proj of projects) {
                        const services = await api.services.listByProject(proj.id);
                        activeCount += services.filter(s => s.is_active).length;
                    }
                } catch(e) { /* skip */ }
            }

            this.animateNumber('stat-active', activeCount);
            this.animateNumber('stat-expiring', expiring.length);
            this.animateNumber('stat-high', high.length);
            this.animateNumber('stat-clients', clients.length);

            document.getElementById('expiring-count-badge').textContent = expiring.length;
            document.getElementById('high-count-badge').textContent = high.length;

            // Render expiring table
            ui.renderTable('expiring-list', [
                { label: 'Service', render: r => `${ui.typeBadge(r.service_type)} <span style="margin-left:6px">Project #${r.project_id}</span>` },
                { label: 'Expiry', key: 'end_date' },
                { label: 'Utilization', render: r => ui.utilBar(r.active_user_count || 0, r.user_limit || 1) }
            ], expiring);

            // Render high utilization
            ui.renderTable('high-util-list', [
                { label: 'Service', render: r => `${ui.typeBadge(r.service_type)} <span style="margin-left:6px">Project #${r.project_id}</span>` },
                { label: 'Users', render: r => `${r.active_user_count} / ${r.user_limit}` },
                { label: 'Utilization', render: r => ui.utilBar(r.active_user_count || 0, r.user_limit || 1) }
            ], high);

        } catch(e) {
            ui.showToast(e.message, 'error');
        }
    },

    // ===================== CLIENTS =====================
    _clientsData: [],

    async loadClients() {
        ui.setBreadcrumb([
            { label: 'Dashboard', href: '#dashboard' },
            { label: 'Clients', href: '#clients' }
        ]);

        try {
            const clients = await api.clients.list();
            this._clientsData = clients;
            this.renderClientTable(clients);
        } catch(e) {
            ui.showToast(e.message, 'error');
        }
    },

    renderClientTable(clients) {
        ui.renderTable('clients-list', [
            { label: '#', render: (r, i) => `<span style="color:var(--text-muted)">${i + 1}</span>` },
            { label: 'Client Name', render: r => `<a href="javascript:void(0)" onclick="app.showClientDetails(${r.id})" style="color:var(--accent-blue);font-weight:600;text-decoration:underline">${r.name}</a>` },
            { label: 'Domain', render: r => r.domain || '<span style="color:var(--text-muted)">—</span>' },
            { label: 'Contact', render: r => r.contact_info || '<span style="color:var(--text-muted)">—</span>' },
            { label: 'Created', render: r => r.created_at ? new Date(r.created_at).toLocaleDateString() : '—' }
        ], clients, [
            { label: 'Projects', handler: 'app.loadProjects', class: 'action-btn action-btn-primary' },
            { label: 'Edit', handler: 'app.showEditClientModal', class: 'action-btn action-btn-amber' },
            { label: 'Delete', handler: 'app.deleteClient', class: 'action-btn action-btn-danger' }
        ]);
    },

    async showClientDetails(id) {
        try {
            const client = await api.clients.get(id);
            ui.showModal('Client Details', `
                <div class="fade-in">
                    <div class="form-group">
                        <label class="form-label">Client Name</label>
                        <div class="form-input" style="background:var(--bg-card);border-color:var(--border-color)">${client.name}</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Domain</label>
                        <div class="form-input" style="background:var(--bg-card);border-color:var(--border-color)">${client.domain || '—'}</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Info</label>
                        <div class="form-textarea" style="background:var(--bg-card);border-color:var(--border-color);min-height:auto">${client.contact_info || '—'}</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Created At</label>
                        <div class="form-input" style="background:var(--bg-card);border-color:var(--border-color)">${new Date(client.created_at).toLocaleString()}</div>
                    </div>
                    <button class="btn btn-secondary btn-block" onclick="ui.closeModal()">Close</button>
                </div>
            `);
        } catch(e) { ui.showToast(e.message, 'error'); }
    },

    filterClients(query) {
        const q = query.toLowerCase();
        const filtered = this._clientsData.filter(c =>
            (c.name || '').toLowerCase().includes(q) ||
            (c.domain || '').toLowerCase().includes(q) ||
            (c.contact_info || '').toLowerCase().includes(q)
        );
        this.renderClientTable(filtered);
    },

    async deleteClient(id) {
        ui.showConfirm('Delete Client', 'This will permanently remove this client and all associated projects, services, and users.', async () => {
            try {
                await api.clients.delete(id);
                ui.showToast('Client deleted successfully');
                this.loadClients();
            } catch(e) {
                ui.showToast(e.message, 'error');
            }
        });
    },

    showAddClientModal() {
        ui.showModal('Add New Client', `
            <form id="form-client" class="fade-in">
                <div class="form-group">
                    <label class="form-label">Client Name *</label>
                    <input type="text" name="name" required class="form-input" placeholder="e.g. Acme Corporation">
                </div>
                <div class="form-group">
                    <label class="form-label">Domain</label>
                    <input type="text" name="domain" class="form-input" placeholder="e.g. acme.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Info</label>
                    <textarea name="contact_info" class="form-textarea" placeholder="Email, phone, notes..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Save Client
                </button>
            </form>
        `);
        document.getElementById('form-client').onsubmit = async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            try {
                await api.clients.create(Object.fromEntries(fd.entries()));
                ui.closeModal();
                ui.showToast('Client created successfully');
                this.loadClients();
            } catch(err) { ui.showToast(err.message, 'error'); }
        };
    },

    async showEditClientModal(id) {
        try {
            const client = await api.clients.get(id);
            ui.showModal('Edit Client', `
                <form id="form-edit-client" class="fade-in">
                    <div class="form-group">
                        <label class="form-label">Client Name *</label>
                        <input type="text" name="name" required class="form-input" value="${client.name || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Domain</label>
                        <input type="text" name="domain" class="form-input" value="${client.domain || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Info</label>
                        <textarea name="contact_info" class="form-textarea">${client.contact_info || ''}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Update Client</button>
                </form>
            `);
            document.getElementById('form-edit-client').onsubmit = async (e) => {
                e.preventDefault();
                const fd = new FormData(e.target);
                try {
                    await api.clients.update(id, Object.fromEntries(fd.entries()));
                    ui.closeModal();
                    ui.showToast('Client updated successfully');
                    this.loadClients();
                } catch(err) { ui.showToast(err.message, 'error'); }
            };
        } catch(err) { ui.showToast(err.message, 'error'); }
    },

    // ===================== PROJECTS =====================
    async loadProjects(clientId, updateHash = true) {
        if (updateHash) { this.showSection('projects', clientId); return; }
        this.currentClientId = clientId;

        // Try to get client name
        try {
            const client = await api.clients.get(clientId);
            this.currentClientName = client.name || `Client #${clientId}`;
        } catch(e) {
            this.currentClientName = `Client #${clientId}`;
        }

        ui.setBreadcrumb([
            { label: 'Dashboard', href: '#dashboard' },
            { label: 'Clients', href: '#clients' },
            { label: this.currentClientName, href: `#projects/${clientId}` }
        ]);

        document.getElementById('projects-section-title').textContent = `${this.currentClientName} — Projects`;
        document.getElementById('projects-section-subtitle').textContent = `Manage projects for ${this.currentClientName}`;
        document.getElementById('projects-client-name').textContent = 'All Projects';

        try {
            const projects = await api.projects.listByClient(clientId);
            ui.renderTable('projects-list', [
                { label: '#', render: (r, i) => `<span style="color:var(--text-muted)">${i + 1}</span>` },
                { label: 'Project Name', render: r => `<span style="color:var(--text-primary);font-weight:600">${r.name}</span>` },
                { label: 'Domain', render: r => r.domain || '<span style="color:var(--text-muted)">—</span>' },
                { label: 'API Key', render: r => r.api_key ? `<code style="background:rgba(255,255,255,0.05);padding:2px 6px;border-radius:4px;font-family:monospace;color:var(--accent-amber)">${r.api_key}</code>` : '<span style="color:var(--text-muted)">No key</span>' },
                { label: 'Created', render: r => r.created_at ? new Date(r.created_at).toLocaleDateString() : '—' }
            ], projects, [
                { label: 'Services', handler: 'app.loadServices', class: 'action-btn action-btn-primary' },
                { 
                    label: 'Generate Key', 
                    handler: 'app.generateApiKey', 
                    class: 'action-btn action-btn-emerald',
                    hide: r => !!r.api_key // Custom logic to hide if key exists
                },
                { label: 'Edit', handler: 'app.showEditProjectModal', class: 'action-btn action-btn-amber' },
                { label: 'Delete', handler: 'app.deleteProject', class: 'action-btn action-btn-danger' }
            ]);
        } catch(e) { ui.showToast(e.message, 'error'); }
    },

    async generateApiKey(id) {
        try {
            // Check if project already has a key
            const project = await api.projects.get(id);
            if (project.api_key) {
                ui.showToast('Project already has an API key.', 'warning');
                return;
            }

            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let key = '';
            for (let i = 0; i < 16; i++) key += chars.charAt(Math.floor(Math.random() * chars.length));
        
            await api.projects.update(id, { api_key: key });
            ui.showToast('API key generated successfully');
            this.loadProjects(this.currentClientId, false);
        } catch(e) { ui.showToast(e.message, 'error'); }
    },

    async deleteProject(id) {
        ui.showConfirm('Delete Project', 'This will remove the project and all its services. Continue?', async () => {
            try {
                await api.projects.delete(id);
                ui.showToast('Project deleted');
                this.loadProjects(this.currentClientId, false);
            } catch(e) { ui.showToast(e.message, 'error'); }
        });
    },

    showAddProjectModal() {
        ui.showModal('Add New Project', `
            <form id="form-project" class="fade-in">
                <div class="form-group">
                    <label class="form-label">Project Name *</label>
                    <input type="text" name="name" required class="form-input" placeholder="e.g. ERP System">
                </div>
                <div class="form-group">
                    <label class="form-label">Project Domain</label>
                    <input type="text" name="domain" class="form-input" placeholder="e.g. project.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" placeholder="Brief project overview..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Save Project
                </button>
            </form>
        `);
        document.getElementById('form-project').onsubmit = async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const data = Object.fromEntries(fd.entries());
            data.client_id = this.currentClientId;
            try {
                await api.projects.create(data);
                ui.closeModal();
                ui.showToast('Project created');
                this.loadProjects(this.currentClientId, false);
            } catch(err) { ui.showToast(err.message, 'error'); }
        };
    },

    async showEditProjectModal(id) {
        try {
            const project = await api.projects.get(id);
            ui.showModal('Edit Project', `
                <form id="form-edit-project" class="fade-in">
                    <div class="form-group">
                        <label class="form-label">Project Name *</label>
                        <input type="text" name="name" required class="form-input" value="${project.name || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Project Domain</label>
                        <input type="text" name="domain" class="form-input" value="${project.domain || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea">${project.description || ''}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Update Project</button>
                </form>
            `);
            document.getElementById('form-edit-project').onsubmit = async (e) => {
                e.preventDefault();
                const fd = new FormData(e.target);
                try {
                    await api.projects.update(id, Object.fromEntries(fd.entries()));
                    ui.closeModal();
                    ui.showToast('Project updated successfully');
                    this.loadProjects(this.currentClientId, false);
                } catch(err) { ui.showToast(err.message, 'error'); }
            };
        } catch(err) { ui.showToast(err.message, 'error'); }
    },

    // ===================== SERVICES =====================
    async loadServices(projectId, updateHash = true) {
        if (updateHash) { this.showSection('services', projectId); return; }
        this.currentProjectId = projectId;

        try {
            const project = await api.projects.get(projectId);
            this.currentProjectName = project.name || `Project #${projectId}`;
        } catch(e) {
            this.currentProjectName = `Project #${projectId}`;
        }

        ui.setBreadcrumb([
            { label: 'Dashboard', href: '#dashboard' },
            { label: 'Clients', href: '#clients' },
            { label: this.currentClientName || 'Client', href: `#projects/${this.currentClientId}` },
            { label: this.currentProjectName, href: `#services/${projectId}` }
        ]);

        document.getElementById('services-section-title').textContent = `${this.currentProjectName} — Services`;
        document.getElementById('services-section-subtitle').textContent = 'Manage service subscriptions and user limits';
        document.getElementById('services-project-name').textContent = 'All Services';

        try {
            const services = await api.services.listByProject(projectId);
            ui.renderTable('services-list', [
                { label: '#', render: (r, i) => `<span style="color:var(--text-muted)">${i + 1}</span>` },
                { label: 'Type', render: r => ui.typeBadge(r.service_type) },
                { label: 'Users', render: r => `<span style="font-weight:600">${r.active_user_count}</span> <span style="color:var(--text-muted)">/ ${r.user_limit}</span>` },
                { label: 'Utilization', render: r => ui.utilBar(r.active_user_count || 0, r.user_limit || 1) },
                { label: 'Status', render: r => ui.statusBadge(r.is_active) },
                { label: 'Start', key: 'start_date' },
                { label: 'End', key: 'end_date' }
            ], services, [
                { label: 'Renew', handler: 'app.showRenewModal', class: 'action-btn action-btn-emerald' },
                { label: 'Extend', handler: 'app.showExtendModal', class: 'action-btn action-btn-amber' },
                { label: 'Edit', handler: 'app.showEditServiceModal', class: 'action-btn action-btn-primary' },
                { label: 'Delete', handler: 'app.deleteService', class: 'action-btn action-btn-danger' }
            ]);
        } catch(e) { ui.showToast(e.message, 'error'); }
    },

    async deleteService(id) {
        ui.showConfirm('Delete Service', 'This will permanently remove this service subscription. Continue?', async () => {
            try {
                await api.services.delete(id);
                ui.showToast('Service deleted');
                this.loadServices(this.currentProjectId, false);
            } catch(e) { ui.showToast(e.message, 'error'); }
        });
    },

    showAddServiceModal() {
        const today = new Date().toISOString().split('T')[0];
        ui.showModal('Add New Service', `
            <form id="form-service" class="fade-in">
                <div class="form-group">
                    <label class="form-label">Service Type *</label>
                    <select name="service_type" class="form-select" required>
                        <option value="web">Web</option>
                        <option value="mobile">Mobile</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">User Limit *</label>
                    <input type="number" name="user_limit" required min="1" class="form-input" placeholder="e.g. 100">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" required class="form-input" value="${today}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date *</label>
                        <input type="date" name="end_date" required class="form-input">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Save Service
                </button>
            </form>
        `);
        document.getElementById('form-service').onsubmit = async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const data = Object.fromEntries(fd.entries());
            data.project_id = this.currentProjectId;
            try {
                await api.services.create(data);
                ui.closeModal();
                ui.showToast('Service created');
                this.loadServices(this.currentProjectId, false);
            } catch(err) { ui.showToast(err.message, 'error'); }
        };
    },

    async showEditServiceModal(id) {
        try {
            const s = await api.services.get(id);
            ui.showModal('Edit Service', `
                <form id="form-edit-service" class="fade-in">
                    <div class="form-group">
                        <label class="form-label">Service Type *</label>
                        <select name="service_type" class="form-select" required>
                            <option value="web" ${s.service_type === 'web' ? 'selected' : ''}>Web</option>
                            <option value="mobile" ${s.service_type === 'mobile' ? 'selected' : ''}>Mobile</option>
                            <option value="other" ${s.service_type === 'other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">User Limit *</label>
                        <input type="number" name="user_limit" required min="1" class="form-input" value="${s.user_limit}">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="start_date" required class="form-input" value="${s.start_date}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date *</label>
                            <input type="date" name="end_date" required class="form-input" value="${s.end_date}">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Update Service</button>
                </form>
            `);
            document.getElementById('form-edit-service').onsubmit = async (e) => {
                e.preventDefault();
                const fd = new FormData(e.target);
                try {
                    await api.services.update(id, Object.fromEntries(fd.entries()));
                    ui.closeModal();
                    ui.showToast('Service updated successfully');
                    this.loadServices(this.currentProjectId, false);
                } catch(err) { ui.showToast(err.message, 'error'); }
            };
        } catch(err) { ui.showToast(err.message, 'error'); }
    },

    async showRenewModal(id) {
        try {
            const s = await api.services.get(id);
            ui.showModal('Renew Subscription', `
                <form id="form-renew" class="fade-in">
                    <div class="form-group">
                        <label class="form-label">Current Period</label>
                        <div class="form-input" style="background:var(--bg-card);border-color:var(--border-color);opacity:0.8">
                            ${s.start_date} to ${s.end_date}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New End Date *</label>
                        <input type="date" name="new_end_date" required class="form-input" min="${s.end_date}">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        Renew Subscription
                    </button>
                </form>
            `);
            document.getElementById('form-renew').onsubmit = async (e) => {
                e.preventDefault();
                const fd = new FormData(e.target);
                try {
                    await api.services.renew(id, { new_end_date: fd.get('new_end_date') });
                    ui.closeModal();
                    ui.showToast('Subscription renewed');
                    this.loadServices(this.currentProjectId, false);
                } catch(err) { ui.showToast(err.message, 'error'); }
            };
        } catch(err) { ui.showToast(err.message, 'error'); }
    },

    async showExtendModal(id) {
        try {
            const s = await api.services.get(id);
            ui.showModal('Extend Subscription', `
                <form id="form-extend" class="fade-in">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Current Limit</label>
                            <div class="form-input" style="background:var(--bg-card);border-color:var(--border-color);opacity:0.8">${s.user_limit}</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Current Expiry</label>
                            <div class="form-input" style="background:var(--bg-card);border-color:var(--border-color);opacity:0.8">${s.end_date}</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New User Limit *</label>
                        <input type="number" name="new_user_limit" required min="${s.user_limit}" class="form-input" value="${s.user_limit}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">New End Date (optional)</label>
                        <input type="date" name="new_end_date" class="form-input" min="${s.end_date}">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
                        Extend Subscription
                    </button>
                </form>
            `);
            document.getElementById('form-extend').onsubmit = async (e) => {
                e.preventDefault();
                const fd = new FormData(e.target);
                const data = { new_user_limit: fd.get('new_user_limit') };
                if (fd.get('new_end_date')) data.new_end_date = fd.get('new_end_date');
                try {
                    await api.services.extend(id, data);
                    ui.closeModal();
                    ui.showToast('Subscription extended');
                    this.loadServices(this.currentProjectId, false);
                } catch(err) { ui.showToast(err.message, 'error'); }
            };
        } catch(err) { ui.showToast(err.message, 'error'); }
    },

    // ===================== ACTIVITY LOG =====================
    async loadActivityLog() {
        ui.setBreadcrumb([
            { label: 'Dashboard', href: '#dashboard' },
            { label: 'Activity Log', href: '#activity' }
        ]);

        try {
            const result = await api.activityLog.list(100);
            const logs = result.data || [];
            
            ui.renderTable('activity-log-list', [
                { label: 'Time', render: r => `<span style="color:var(--text-muted);font-size:0.75rem">${new Date(r.created_at).toLocaleString()}</span>` },
                { label: 'Entity', render: r => `<span class="card-badge badge-blue">${r.entity_type.toUpperCase()}</span>` },
                { label: 'Action', render: r => `<span class="status-badge ${r.action.includes('fail') ? 'status-expired' : 'status-active'}">${r.action.replace('_', ' ').toUpperCase()}</span>` },
                { label: 'Description', render: r => `<span style="color:var(--text-primary)">${r.description}</span>` },
                { label: 'IP Address', render: r => `<span style="color:var(--text-muted)">${r.ip_address || '—'}</span>` }
            ], logs);
        } catch(e) { ui.showToast(e.message, 'error'); }
    },

    // ===================== REPORTING =====================
    async loadReporting() {
        ui.setBreadcrumb([
            { label: 'Dashboard', href: '#dashboard' },
            { label: 'Reports', href: '#reporting' }
        ]);

        const days = parseInt(document.getElementById('report-expiry-days').value);
        const threshold = parseInt(document.getElementById('report-util-threshold').value);

        try {
            const [expiring, high] = await Promise.all([
                api.reporting.expiring(days),
                api.reporting.highUtilization(threshold)
            ]);

            ui.renderTable('report-expiring-list', [
                { label: 'Service', render: r => `${ui.typeBadge(r.service_type)} <span style="margin-left:6px">Project #${r.project_id}</span>` },
                { label: 'Expiry Date', key: 'end_date' },
                { label: 'Utilization', render: r => ui.utilBar(r.active_user_count || 0, r.user_limit || 1) },
                { label: 'Status', render: r => ui.statusBadge(r.is_active) }
            ], expiring);

            ui.renderTable('report-high-list', [
                { label: 'Service', render: r => `${ui.typeBadge(r.service_type)} <span style="margin-left:6px">Project #${r.project_id}</span>` },
                { label: 'Users', render: r => `<span style="font-weight:600">${r.active_user_count}</span> / ${r.user_limit}` },
                { label: 'Utilization', render: r => ui.utilBar(r.active_user_count || 0, r.user_limit || 1) }
            ], high);

        } catch(e) { ui.showToast(e.message, 'error'); }
    },

    // ===================== HELPERS =====================
    animateNumber(elId, target) {
        const el = document.getElementById(elId);
        if (!el) return;
        const start = parseInt(el.textContent) || 0;
        const diff = target - start;
        if (diff === 0) { el.textContent = target; return; }
        const duration = 600;
        const startTime = performance.now();
        const step = (now) => {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(start + diff * ease);
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }
};

// Boot
document.addEventListener('DOMContentLoaded', () => {
    app.init();
    
    // Activity refresh button wiring (extra safety)
    const refreshAct = document.getElementById('btn-refresh-activity');
    if (refreshAct) refreshAct.onclick = () => app.loadActivityLog();
});
