export async function setupNeonAuth({ api, toast, currentModule }) {
    const form = document.querySelector('#login-form');
    const token = new URLSearchParams(location.search).get('token');
    let signup = false;
    let submitting = false;
    const toggle = document.querySelector('#toggle-signup');
    function mode() {
        document.querySelector('#signup-name').hidden = !signup;
        form.elements.name.required = signup;
        form.elements.password.autocomplete = signup ? 'new-password' : 'current-password';
        form.querySelector('[type="submit"]').textContent = signup ? (new URLSearchParams(location.search).has('convite') ? 'Criar minha conta de associado' : 'Criar meu escritório') : 'Entrar no escritório';
        toggle.textContent = signup ? 'Já tenho conta' : 'Criar conta';
    }
    toggle?.addEventListener('click', () => { signup = !signup; mode(); });
    if (form && token) {
        form.elements.email.closest('label').hidden = true;
        form.elements.email.required = false;
        form.elements.password.autocomplete = 'new-password';
        form.querySelector('[type="submit"]').textContent = 'Salvar nova senha';
        document.querySelector('.login-links').hidden = true;
    }
    form?.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting) return;
        const button = form.querySelector('[type="submit"]');
        const error = form.querySelector('.form-error');
        const originalLabel = button.innerHTML;
        const status = document.querySelector('#auth-status');
        let redirecting = false;
        let modeChanged = false;
        submitting = true;
        button.disabled = true; error.hidden = true;
        button.setAttribute('aria-busy', 'true');
        form.setAttribute('aria-busy', 'true');
        button.classList.add('is-loading');
        button.textContent = token ? 'Salvando senha…' : signup ? 'Criando sua conta…' : 'Entrando…';
        toggle.disabled = true;
        document.querySelector('#recover-account').disabled = true;
        status.hidden = false;
        status.textContent = token ? 'Aguarde enquanto salvamos sua senha.' : signup ? 'Preparando seu acesso ao escritório.' : 'Validando seu acesso ao escritório.';
        try {
            const action = token ? 'reset' : signup ? 'register' : 'login';
            const data = token ? { token, newPassword: form.elements.password.value } : {
                email: form.elements.email.value, password: form.elements.password.value, invitation: new URLSearchParams(location.search).get('convite') || undefined,
                ...(signup ? { name: form.elements.name.value } : {})
            };
            const result = await api(`/auth/neon/${action}`, { method: 'POST', data });
            form.elements.password.value = '';
            if (result.redirect || token) {
                redirecting = true;
                button.textContent = 'Abrindo seu escritório…';
                status.textContent = token ? 'Senha salva. Abrindo a tela de acesso.' : 'Acesso confirmado. Abrindo seu escritório.';
                location.href = result.redirect || '/entrar'; return;
            }
            signup = false; mode(); modeChanged = true; toast(result.message);
        } catch (exception) { error.textContent = exception.message; error.hidden = false; }
        finally {
            if (!redirecting) {
                submitting = false;
                button.disabled = false;
                button.innerHTML = modeChanged ? 'Entrar no escritório' : originalLabel;
                button.removeAttribute('aria-busy'); form.removeAttribute('aria-busy');
                button.classList.remove('is-loading');
                toggle.disabled = false;
                document.querySelector('#recover-account').disabled = false;
                status.hidden = true;
            }
        }
    });
    document.querySelector('#recover-account')?.addEventListener('click', async () => {
        const email = form.elements.email;
        if (!email.value || !email.checkValidity()) { email.reportValidity(); return; }
        try {
            await api('/auth/neon/recover', { method: 'POST', data: { email: email.value } });
            toast('Se o e-mail estiver cadastrado, você receberá as instruções de recuperação.');
        } catch (error) { toast(error.message, true); }
    });
    if (currentModule && document.querySelector('meta[name="neon-session-active"]')?.content === '1') {
        const expiresAt = Number(document.querySelector('meta[name="neon-session-expires-at"]')?.content || 0) * 1000;
        let refreshAfter = expiresAt ? expiresAt - 5 * 60 * 1000 : Date.now() + 10 * 60 * 1000;
        let lastAttempt = 0;
        let refreshing = false;
        const refresh = async () => {
            if (document.hidden || refreshing || Date.now() < refreshAfter || Date.now() - lastAttempt < 60 * 1000) return;
            refreshing = true;
            lastAttempt = Date.now();
            try {
                await api('/auth/neon/refresh', { method: 'POST', data: {} });
                refreshAfter = Date.now() + 10 * 60 * 1000;
            }
            catch (error) { toast(error.message, true); }
            finally { refreshing = false; }
        };
        setInterval(refresh, 60 * 1000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
        refresh();
    }
}
