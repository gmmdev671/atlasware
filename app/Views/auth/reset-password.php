<?php
// app/Views/auth/reset-password.php
// Espera-se as variáveis: $title, $basePath, $user_id, $user_name, $user_login, $errors (array)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Gera CSRF token simples (se ainda não existir)
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // fallback
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrfToken = $_SESSION['csrf_token'];

$displayName = $user_name ?? $user_login ?? 'Usuário';
?>
<div class="auth-reset-container" style="max-width:480px;margin:40px auto;padding:18px;border:1px solid #ddd;border-radius:6px;background:#fff;">
    <h2 style="margin-top:0;">Redefinir senha</h2>

    <p>Olá <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></strong>. Escolha uma nova senha para sua conta.</p>

    <?php if (!empty($errors) && is_array($errors)): ?>
        <div style="background:#ffe6e6;border:1px solid #ffb3b3;padding:10px;margin-bottom:12px;border-radius:4px;color:#900;">
            <ul style="margin:0;padding-left:18px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars(($basePath ?? '') . '/auth/reset-password', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div style="margin-bottom:12px;">
            <label for="password" style="display:block;margin-bottom:6px;">Nova senha</label>
            <input id="password" name="password" type="password" autocomplete="new-password"
                   style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;" required minlength="8">
        </div>

        <div style="margin-bottom:12px;">
            <label for="password_confirm" style="display:block;margin-bottom:6px;">Confirme a nova senha</label>
            <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password"
                   style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;" required minlength="8">
        </div>

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
            <input id="show_pwd" type="checkbox" />
            <label for="show_pwd" style="margin:0;">Mostrar senhas</label>
        </div>

        <div style="display:flex;gap:8px;">
            <button type="submit" style="padding:10px 14px;background:#007bff;color:#fff;border:none;border-radius:4px;cursor:pointer;">
                Salvar nova senha
            </button>
            <a href="<?= htmlspecialchars(($basePath ?? '') . '/login', ENT_QUOTES, 'UTF-8') ?>" style="align-self:center;color:#555;text-decoration:none;">
                Voltar ao login
            </a>
        </div>

        <p style="margin-top:14px;color:#666;font-size:13px;">
            Dica: use ao menos 8 caracteres. Em ambiente de produção, políticas de complexidade podem ser aplicadas.
        </p>
    </form>
</div>

<script>
(function() {
    const pwd = document.getElementById('password');
    const pwd2 = document.getElementById('password_confirm');
    const togg = document.getElementById('show_pwd');

    togg.addEventListener('change', function() {
        const t = togg.checked ? 'text' : 'password';
        pwd.type = t;
        pwd2.type = t;
    });

    // Client-side helper: quick validation antes do submit (opcional)
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const p = pwd.value || '';
        const p2 = pwd2.value || '';
        const errors = [];

        if (p.length < 8) errors.push('Senha muito curta (mínimo 8 caracteres).');
        if (p !== p2) errors.push('As senhas não coincidem.');

        if (errors.length) {
            e.preventDefault();
            alert(errors.join("\\n"));
        }
    });
})();
</script>