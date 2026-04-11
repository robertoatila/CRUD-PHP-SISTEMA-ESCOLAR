<?php
// includes/layout.php — cabeçalho + sidebar compartilhados
// Chame: layout_start($title, $page)  e  layout_end()

function layout_start(string $title, string $page = ''): void {
    global $ROLE_NAMES, $ROLE_COLORS;
    $user  = currentUser();
    $cargo = (int)($user['cargo'] ?? 99);
    $nome  = $user['nome'] ?? '';
    $init  = mb_strtoupper(mb_substr($nome, 0, 1));
    $roleN = $ROLE_NAMES[$cargo]  ?? '';
    $roleC = $ROLE_COLORS[$cargo] ?? '#888';
    $base  = '/escola'; 
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title) ?> — EduGestor</title>
<link rel="stylesheet" href="/escola/assets/style.css">
</head>
<body>
<div class="app">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">🎓</div>
    <div>
      <h1>EduGestor</h1>
      <span>v2.1.0 · Sistema Escolar</span>
    </div>
  </div>

  <div class="sidebar-user">
    <div class="avatar"><?=$init?></div>
    <div class="info">
      <div class="name"><?=htmlspecialchars($nome)?></div>
      <div class="role" style="color:<?=$roleC?>"><?=$roleN?></div>
    </div>
  </div>

  <nav>
    <div class="nav-section">
      <div class="nav-label">Principal</div>
      <a class="nav-item <?=$page==='dashboard'?'active':''?>" href="<?=$base?>/dashboard.php">
        <span class="icon">🏠</span> Dashboard
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Acadêmico</div>
      <a class="nav-item <?=$page==='alunos'?'active':''?>" href="<?=$base?>/alunos.php">
        <span class="icon">👨‍🎓</span> Alunos
      </a>
      <a class="nav-item <?=$page==='professores'?'active':''?>" href="<?=$base?>/professores.php">
        <span class="icon">👨‍🏫</span> Professores
      </a>
      <a class="nav-item <?=$page==='notas'?'active':''?>" href="<?=$base?>/notas.php">
        <span class="icon">📝</span> Notas
      </a>
      <a class="nav-item <?=$page==='presenca'?'active':''?>" href="<?=$base?>/presenca.php">
        <span class="icon">📅</span> Presença
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Relatórios</div>
      <a class="nav-item <?=$page==='relatorios'?'active':''?>" href="<?=$base?>/relatorios.php">
        <span class="icon">📊</span> Relatórios
      </a>
    </div>

    <?php if (currentCargo() === ROLE_ADMIN): ?>
    <div class="nav-section">
      <div class="nav-label">Administração</div>
      <a class="nav-item <?=$page==='usuarios'?'active':''?>" href="<?=$base?>/usuarios.php">
        <span class="icon">👥</span> Usuários
      </a>
      <a class="nav-item <?=$page==='tokens'?'active':''?>" href="<?=$base?>/tokens.php">
        <span class="icon">🔑</span> Tokens de API
      </a>
    </div>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <a class="logout-btn" href="<?=$base?>/logout.php">
      <span>🚪</span> Sair do sistema
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <header class="topbar">
    <div class="topbar-left">
      <button class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
      <h2><?=htmlspecialchars($title)?></h2>
    </div>
    <div class="topbar-right">
      <span class="badge" style="background:<?=$roleC?>"><?=$roleN?></span>
      <span style="font-size:.8rem;color:var(--muted)"><?=date('d/m/Y H:i')?></span>
    </div>
  </header>

  <div class="content fade-in">
<?php
}

function layout_end(): void {
    ?>
  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /app -->

<script src="/crud/escola/assets/app.js"></script>
</body>
</html>
<?php
}
