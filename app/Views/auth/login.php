<?php
// app/Views/auth/login.php
?>
<div class="login-container">
    <h2>Login</h2>

    <?php if (!empty($error)): ?>
        <div class="error-message">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($basePath . '/login', ENT_QUOTES, 'UTF-8') ?>">
        <div>
            <label for="login">Login</label>
            <input
                type="text"
                id="login"
                name="login"
                required
                autocomplete="username"
            >
        </div>

        <div>
            <label for="password">Senha</label>
            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
            >
        </div>

        <button type="submit">
            Entrar
        </button>
    </form>
</div>