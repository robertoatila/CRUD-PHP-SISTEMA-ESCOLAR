<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user'])) {
    header('Location: /escola/dashboard.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    if (!$login || !$senha) {
        $erro = 'Preencha todos os campos.';
    } elseif (!login($login, $senha)) {
        $erro = 'Login ou senha incorretos.';
    } else {
        header('Location: /escola/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — EduGestor</title>
<link rel="stylesheet" href="/escola/assets/style.css">
<style>
.input-wrap{position:relative}
.input-wrap .toggle-pwd{position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:.9rem;padding:.2rem}
.divider{display:flex;align-items:center;gap:.8rem;margin:1.2rem 0;color:var(--muted);font-size:.75rem}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}
</style>
</head>
<body>
<div class="login-wrap">

  <!-- Lado esquerdo — branding -->
  <div class="login-left">
    <div style="position:relative;z-index:1;width:100%;max-width:340px">
      <div class="hero-icon">🎓</div>
      <h1>EduGestor</h1>
      <p>Sistema completo de gerenciamento escolar com integração à nuvem</p>

      <div class="features">
        <div class="feature-item"><span>👨‍🎓</span> Gestão de alunos e matrículas</div>
        <div class="feature-item"><span>📝</span> Controle de notas por bimestre</div>
        <div class="feature-item"><span>📅</span> Registro de presença digital</div>
        <div class="feature-item"><span>📊</span> Relatórios semanais automáticos</div>
        <div class="feature-item"><span>☁️</span> Sincronização com Supabase Cloud</div>
        <div class="feature-item"><span>🔑</span> Gestão de tokens de API</div>
      </div>
    </div>
  </div>

  <!-- Lado direito — formulário -->
  <div class="login-right">
    <div class="login-box">
      <h2>Bem-vindo de volta</h2>
      <p class="subtitle">Faça login para acessar o painel escolar</p>

      <?php if ($erro): ?>
        <div class="alert alert-error">⚠️ <?=htmlspecialchars($erro)?></div>
      <?php endif; ?>

      <?php if (isset($_GET['msg']) && $_GET['msg']==='session_expired'): ?>
        <div class="alert alert-warn">⏰ Sessão expirada. Faça login novamente.</div>
      <?php endif; ?>

      <form method="POST" action="" autocomplete="off">
        <div class="form-group mb-3">
          <label>Login</label>
          <input type="text" name="login" class="form-control" placeholder="Digite seu login"
                 value="<?=htmlspecialchars($_POST['login']??'')?>" required autofocus>
        </div>

        <div class="form-group mb-3">
          <label>Senha</label>
          <div class="input-wrap">
            <input type="password" name="senha" id="senhaInput" class="form-control" placeholder="••••••••" required>
            <button type="button" class="toggle-pwd" onclick="togglePwd()">👁</button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-full" style="justify-content:center;padding:.7rem">
          🔐 Entrar no Sistema
        </button>
      </form>

      <div class="divider">credenciais de acesso</div>

      <details class="credential-hints">
        <summary>Ver logins de demonstração</summary>
        <table class="cred-table">
          <thead><tr><th>Cargo</th><th>Hierarquia</th><th>Login</th><th>Senha</th></tr></thead>
          <tbody>
            <tr><td><span class="badge" style="background:#f59e0b">Admin</span></td><td>1</td><td>admin</td><td>admin123</td></tr>
            <tr><td><span class="badge" style="background:#6366f1">Diretor</span></td><td>2</td><td>diretor</td><td>diretor123</td></tr>
            <tr><td><span class="badge" style="background:#10b981">Secretaria</span></td><td>3</td><td>secretaria</td><td>sec123</td></tr>
            <tr><td><span class="badge" style="background:#3b82f6">Professor</span></td><td>4</td><td>professor</td><td>prof123</td></tr>
          </tbody>
        </table>
      </details>

      <p style="font-size:.7rem;color:var(--muted);text-align:center;margin-top:2rem">
        EduGestor v2.1.0 · Todos os direitos reservados
      </p>
    </div>
  </div>
</div>

<script>
function togglePwd(){
  const i = document.getElementById('senhaInput');
  i.type = i.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
