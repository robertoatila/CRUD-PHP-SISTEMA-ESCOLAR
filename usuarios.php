<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireRole(ROLE_ADMIN);

$db = getDB();
$msg = ''; $erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $login = trim($_POST['login']??'');
        $senha = trim($_POST['senha']??'');
        $nome  = trim($_POST['nome']??'');
        $email = trim($_POST['email']??'');
        $cargo = (int)($_POST['cargo']??4);

        if (!$login||!$senha||!$nome) { $erro='Login, senha e nome são obrigatórios.'; }
        else {
            try {
                $db->prepare("INSERT INTO usuarios (login,senha_hash,nome,email,cargo) VALUES (?,?,?,?,?)")
                   ->execute([$login, password_hash($senha,PASSWORD_DEFAULT), $nome, $email, $cargo]);
                logAction(currentId(),'criar','usuario',(int)$db->lastInsertId());
                $msg = "✅ Usuário <strong>$nome</strong> criado com sucesso.";
            } catch (\PDOException $e) {
                $erro = 'Login já existe. Escolha outro.';
            }
        }
    }

    if ($acao === 'editar') {
        $id    = (int)($_POST['id']??0);
        $nome  = trim($_POST['nome']??'');
        $email = trim($_POST['email']??'');
        $cargo = (int)($_POST['cargo']??4);
        $db->prepare("UPDATE usuarios SET nome=?,email=?,cargo=?,updated_at=datetime('now') WHERE id=?")->execute([$nome,$email,$cargo,$id]);
        if (!empty($_POST['nova_senha'])) {
            $db->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?")->execute([password_hash(trim($_POST['nova_senha']),PASSWORD_DEFAULT),$id]);
        }
        logAction(currentId(),'editar','usuario',$id);
        $msg = "✅ Usuário atualizado.";
    }

    if ($acao === 'toggle') {
        $id  = (int)($_POST['id']??0);
        $cur = $db->prepare("SELECT ativo FROM usuarios WHERE id=?"); $cur->execute([$id]);
        $cur = $cur->fetchColumn();
        $db->prepare("UPDATE usuarios SET ativo=? WHERE id=?")->execute([$cur?0:1,$id]);
        $msg = "✅ Status atualizado.";
    }
}

$usuarios = $db->query("SELECT * FROM usuarios ORDER BY cargo, nome")->fetchAll();

layout_start('Usuários','usuarios');
?>

<?php if ($msg):  ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-error">⚠️ <?=htmlspecialchars($erro)?></div><?php endif; ?>

<!-- Hierarquia visual -->
<div class="card mb-4">
  <div class="card-header"><span class="card-title">🏛️ Hierarquia de Cargos</span></div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;text-align:center">
    <?php
    $hier = [
      [ROLE_ADMIN,'👑','Administrador','Acesso total ao sistema, usuários, tokens e relatórios.','#f59e0b'],
      [ROLE_DIRETOR,'🏫','Diretor','Gestão geral de alunos, professores e relatórios.','#6366f1'],
      [ROLE_SECRETARIA,'📋','Secretaria','Cadastro e edição de alunos e professores.','#10b981'],
      [ROLE_PROFESSOR,'📚','Professor','Lançamento de notas e chamadas das suas turmas.','#3b82f6'],
    ];
    foreach ($hier as [$lvl,$ic,$nm,$desc,$cor]):
    ?>
    <div style="background:var(--bg3);border:1px solid var(--border);border-top:3px solid <?=$cor?>;border-radius:8px;padding:1rem">
      <div style="font-size:1.8rem"><?=$ic?></div>
      <div style="font-weight:700;font-size:.85rem;margin:.3rem 0;color:<?=$cor?>"><?=$nm?></div>
      <div style="font-size:.72rem;color:var(--muted)"><?=$desc?></div>
      <div style="margin-top:.5rem;font-family:var(--font-mono);font-size:.7rem;color:var(--border)">Nível <?=$lvl?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="flex justify-between items-center mb-4">
  <div style="font-size:.8rem;color:var(--muted)"><?=count($usuarios)?> usuário(s)</div>
  <button class="btn btn-primary" onclick="openModal('modal-user')">+ Novo Usuário</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nome</th><th>Login</th><th>E-mail</th><th>Cargo</th><th>Status</th><th>Criado em</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($usuarios as $u): global $ROLE_NAMES,$ROLE_COLORS;
          $cN = $ROLE_NAMES[(int)$u['cargo']] ?? '?';
          $cC = $ROLE_COLORS[(int)$u['cargo']] ?? '#888';
        ?>
        <tr>
          <td class="td-name"><?=htmlspecialchars($u['nome'])?></td>
          <td class="td-code"><?=htmlspecialchars($u['login'])?></td>
          <td style="font-size:.8rem"><?=htmlspecialchars($u['email']??'—')?></td>
          <td><span class="badge" style="background:<?=$cC?>"><?=$cN?></span></td>
          <td><span class="badge <?=$u['ativo']?'badge-success':'badge-danger'?>"><?=$u['ativo']?'Ativo':'Inativo'?></span></td>
          <td class="td-code"><?=date('d/m/Y',strtotime($u['created_at']))?></td>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-ghost btn-xs" onclick='editUser(<?=json_encode($u)?>)'>✏️</button>
              <?php if ($u['id'] !== currentId()): ?>
              <form method="POST">
                <input type="hidden" name="acao" value="toggle">
                <input type="hidden" name="id" value="<?=$u['id']?>">
                <button type="submit" class="btn btn-ghost btn-xs"><?=$u['ativo']?'⏸ Desativar':'▶ Ativar'?></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL CRIAR -->
<div class="modal-backdrop" id="modal-user">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">👤 Novo Usuário</span>
      <button class="modal-close" onclick="closeModal('modal-user')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="acao" value="criar">
        <div class="form-grid">
          <div class="form-group"><label>Nome Completo *</label><input type="text" name="nome" class="form-control" required></div>
          <div class="form-group"><label>Login *</label><input type="text" name="login" class="form-control" required></div>
          <div class="form-group"><label>E-mail</label><input type="email" name="email" class="form-control"></div>
          <div class="form-group"><label>Senha *</label><input type="password" name="senha" class="form-control" required></div>
          <div class="form-group" style="grid-column:1/-1"><label>Cargo / Hierarquia</label>
            <select name="cargo" class="form-control">
              <?php global $ROLE_NAMES;
              foreach ($ROLE_NAMES as $v=>$n): if($v===ROLE_ADMIN && !isAdmin()) continue; ?>
              <option value="<?=$v?>"><?=$n?> (Nível <?=$v?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-user')">Cancelar</button>
          <button type="submit" class="btn btn-primary">💾 Criar Usuário</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal-backdrop" id="modal-edit-user">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">✏️ Editar Usuário</span>
      <button class="modal-close" onclick="closeModal('modal-edit-user')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="acao" value="editar">
        <input type="hidden" name="id" id="eu-id">
        <div class="form-grid">
          <div class="form-group"><label>Nome *</label><input type="text" name="nome" id="eu-nome" class="form-control" required></div>
          <div class="form-group"><label>E-mail</label><input type="email" name="email" id="eu-email" class="form-control"></div>
          <div class="form-group"><label>Cargo</label>
            <select name="cargo" id="eu-cargo" class="form-control">
              <?php foreach ($ROLE_NAMES as $v=>$n): ?><option value="<?=$v?>"><?=$n?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Nova Senha (vazio = manter)</label><input type="password" name="nova_senha" class="form-control" placeholder="Deixe em branco para manter"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-user')">Cancelar</button>
          <button type="submit" class="btn btn-primary">💾 Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editUser(u){
  document.getElementById('eu-id').value    = u.id;
  document.getElementById('eu-nome').value  = u.nome;
  document.getElementById('eu-email').value = u.email||'';
  document.getElementById('eu-cargo').value = u.cargo;
  openModal('modal-edit-user');
}
</script>

<?php layout_end(); ?>
