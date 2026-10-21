let token = localStorage.getItem('token');

async function api(method, path, body) {
    const headers = { 'Accept': 'application/json' };

    if (body) {
        headers['Content-Type'] = 'application/json';
    }

    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }

    const response = await fetch('/api' + path, {
        method: method,
        headers: headers,
        body: body ? JSON.stringify(body) : undefined,
    });

    const text = await response.text();
    let data;

    try {
        data = JSON.parse(text);
    } catch (error) {
        data = text;
    }

    const responseBox = document.getElementById('response');

    if (responseBox) {
        responseBox.textContent =
            method + ' /api' + path + '  →  ' + response.status + '\n\n' + JSON.stringify(data, null, 2);
    }

    return { ok: response.ok, status: response.status, data: data };
}

function saveSession(accessToken, user) {
    token = accessToken;
    localStorage.setItem('token', accessToken);
    localStorage.setItem('userName', user.name);
    localStorage.setItem('userRole', user.user_role ? user.user_role.role.name : '');
}

function clearSession() {
    token = null;
    localStorage.removeItem('token');
    localStorage.removeItem('userName');
    localStorage.removeItem('userRole');
}

function isAdmin() {
    const role = localStorage.getItem('userRole');
    return role === 'SuperAdmin' || role === 'Administrator';
}

function requireLogin() {
    if (!token) {
        window.location.href = 'login.html';
    }
}

async function logout() {
    await api('POST', '/logout');
    clearSession();
    window.location.href = 'login.html';
}

function renderNav() {
    const nav = document.getElementById('nav');
    let html = '<a href="catalog.html">Catálogo</a>';

    if (token) {
        if (isAdmin()) {
            html += '<a href="users.html">Usuarios</a>';
        }
        html += '<span>Hola, ' + escapeHtml(localStorage.getItem('userName')) + '</span>';
        html += '<button onclick="logout()">Cerrar sesión</button>';
    } else {
        html += '<a href="login.html">Iniciar sesión</a>';
        html += '<a href="register.html">Registrarse</a>';
    }

    nav.innerHTML = html;
}

function showMessage(elementId, result, okText) {
    const box = document.getElementById(elementId);
    box.innerHTML = '';

    if (result.ok) {
        box.className = 'ok';
        box.textContent = okText || result.data.message;
        return;
    }

    box.className = 'error';
    const title = document.createElement('p');
    title.textContent = result.data.message || 'Ocurrió un error.';
    box.appendChild(title);

    if (result.data.errors) {
        const list = document.createElement('ul');
        for (const field in result.data.errors) {
            for (const message of result.data.errors[field]) {
                const item = document.createElement('li');
                item.textContent = message;
                list.appendChild(item);
            }
        }
        box.appendChild(list);
    }
}

function addCell(row, text) {
    const cell = document.createElement('td');
    cell.textContent = text;
    row.appendChild(cell);
    return cell;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}