import { createAuthClient } from '@neondatabase/auth';
import { BetterAuthVanillaAdapter } from '@neondatabase/auth/vanilla/adapters';

export async function setupNeonAuth({ neonUrl, api, toast, currentModule }) {
    const auth = createAuthClient(neonUrl, { adapter: BetterAuthVanillaAdapter({ fetchOptions: { credentials: 'include' } }) });
    async function exchange() {
        const { data, error } = await auth.token();
        if (error || !data?.token) throw new Error('Não foi possível validar sua sessão no Neon. Entre novamente.');
        return api('/auth/neon/session', { method: 'POST', headers: { Authorization: `Bearer ${data.token}` }, data: {} });
    }
    const form = document.querySelector('#login-form');
    const resetToken = new URLSearchParams(location.search).get('token');
    if (form && resetToken) {
        form.elements.email.closest('label').hidden = true;
        form.elements.email.required = false;
        form.elements.password.autocomplete = 'new-password';
        form.querySelector('[type="submit"]').textContent = 'Salvar nova senha';
        document.querySelector('.login-links').hidden = true;
        document.querySelector('.login-subtitle').textContent = 'Escolha uma nova senha para seu escritório.';
    }
    let signup = false;
    document.querySelector('#toggle-signup')?.addEventListener('click', event => {
        signup = !signup;
        document.querySelector('#signup-name').hidden = !signup;
        form.elements.name.required = signup;
        form.elements.password.autocomplete = signup ? 'new-password' : 'current-password';
        form.querySelector('[type="submit"]').textContent = signup ? 'Criar meu escritório' : 'Entrar no escritório';
        event.target.textContent = signup ? 'Já tenho conta' : 'Criar conta';
    });
    form?.addEventListener('submit', async event => {
        event.preventDefault(); const button = form.querySelector('[type="submit"]'); const errorNode = form.querySelector('.form-error');
        const original = button.textContent; button.disabled = true; button.textContent = signup ? 'Criando conta…' : 'Entrando…'; errorNode.hidden = true;
        try {
            if (resetToken) {
                const result = await auth.resetPassword({ token: resetToken, newPassword: form.elements.password.value });
                if (result.error) throw new Error('O link de recuperação expirou ou é inválido. Solicite um novo link.');
                toast('Senha atualizada. Entre com sua nova senha.');
                history.replaceState(null, '', '/entrar');
                location.reload();
                return;
            }
            const credentials = { email: form.elements.email.value, password: form.elements.password.value };
            const result = signup ? await auth.signUp.email({ ...credentials, name: form.elements.name.value }) : await auth.signIn.email(credentials);
            if (result.error) throw new Error(result.error.message || 'Confira o e-mail e a senha.');
            const session = await auth.getSession();
            if (!session.data?.user) { toast('Conta criada. Confira seu e-mail para verificar o acesso.'); return; }
            const data = await exchange(); location.href = data.redirect;
        } catch (exception) { errorNode.textContent = exception.message; errorNode.hidden = false; }
        finally { button.disabled = false; button.textContent = original; }
    });
    document.querySelector('#recover-account')?.addEventListener('click', async () => {
        const email = form.elements.email;
        if (!email.value || !email.checkValidity()) { email.reportValidity(); email.focus(); return; }
        try {
            const { error } = await auth.requestPasswordReset({ email: email.value, redirectTo: `${location.origin}/entrar` });
            if (error) throw new Error(error.message || 'Não foi possível solicitar a recuperação.');
            toast('Se o e-mail estiver cadastrado, você receberá as instruções de recuperação.');
        } catch (exception) { toast(exception.message, true); }
    });
    if (form && !resetToken) {
        const result = await auth.getSession().catch(() => null);
        if (result?.data?.user) exchange().then(data => { location.href = data.redirect; }).catch(() => {});
    }
    if (currentModule) {
        const refresh = () => exchange().catch(exception => toast(exception.message, true));
        setInterval(refresh, 10 * 60 * 1000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    }
    document.querySelector('#logout-form')?.addEventListener('submit', async event => {
        event.preventDefault(); const logoutForm = event.target; const button = logoutForm.querySelector('button'); button.disabled = true;
        try { await auth.signOut(); logoutForm.submit(); }
        catch { button.disabled = false; toast('Não foi possível encerrar a sessão. Tente novamente.', true); }
    });
}
