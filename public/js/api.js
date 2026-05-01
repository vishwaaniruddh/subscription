// =========================================
// SubManager — API Client Module
// =========================================

const BASE = '';

async function request(path, method = 'GET', data = null) {
    const token = localStorage.getItem('submanager_token');
    
    const options = {
        method,
        headers: { 
            'Content-Type': 'application/json'
        }
    };

    if (token) {
        options.headers['Authorization'] = `Bearer ${token}`;
    }

    if (data) options.body = JSON.stringify(data);

    const response = await fetch(`${BASE}${path}`, options);
    
    // Auto-logout on 401 Unauthorized
    if (response.status === 401 && !path.includes('api/auth/login')) {
        localStorage.removeItem('submanager_token');
        window.location.reload();
        return;
    }

    const result = await response.json();

    if (!response.ok) {
        throw new Error(result.error || result.message || 'Something went wrong');
    }
    return result;
}

export const api = {
    auth: {
        login: (credentials) => request('api/auth/login', 'POST', credentials),
        validate: () => request('api/auth/validate')
    },
    clients: {
        list:     ()          => request('api/clients'),
        get:      (id)        => request(`api/clients/${id}`),
        create:   (data)      => request('api/clients', 'POST', data),
        update:   (id, data)  => request(`api/clients/${id}`, 'PUT', data),
        delete:   (id)        => request(`api/clients/${id}`, 'DELETE')
    },
    projects: {
        listByClient: (cid)   => request(`api/clients/${cid}/projects`),
        get:          (id)    => request(`api/projects/${id}`),
        create:       (data)  => request('api/projects', 'POST', data),
        update:       (id, d) => request(`api/projects/${id}`, 'PUT', d),
        delete:       (id)    => request(`api/projects/${id}`, 'DELETE')
    },
    services: {
        listByProject: (pid)  => request(`api/projects/${pid}/services`),
        get:           (id)   => request(`api/services/${id}`),
        create:        (data) => request('api/services', 'POST', data),
        update:        (id,d) => request(`api/services/${id}`, 'PUT', d),
        delete:        (id)   => request(`api/services/${id}`, 'DELETE'),
        getStatus:     (id)   => request(`api/services/${id}/status`),
        renew:         (id,d) => request(`api/services/${id}/renew`, 'POST', d),
        extend:        (id,d) => request(`api/services/${id}/extend`, 'POST', d)
    },
    users: {
        listByService: (sid)  => request(`api/services/${sid}/users`),
        register:      (sid,d)=> request(`api/services/${sid}/users`, 'POST', d),
        deactivate:    (uid)  => request(`api/users/${uid}`, 'DELETE')
    },
    reporting: {
        expiring:        (days = 30)       => request(`api/services/expiring?days=${days}`),
        highUtilization: (threshold = 90)  => request(`api/services/high-utilization?threshold=${threshold}`)
    },
    activityLog: {
        list: (limit = 50, offset = 0) => request(`api/activity-log?limit=${limit}&offset=${offset}`)
    }
};
