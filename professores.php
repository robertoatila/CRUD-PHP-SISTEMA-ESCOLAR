<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db  = getDB();
$msg = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && canEdit()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome   = trim($_POST['nome']??'');
        $email  = trim($_POST['email']??'');
        $tel    = trim($_POST['telefone']??'');
        $disc   = trim($_POST['disciplina']??'');
        $form   = trim($_POST['formacao']??'');
        $turmas = trim($_POST['turmas']??'');
        if (!$nome) { $erro = 'Nome é obrigatório.'; }
        else {
            $ano  = date('Y');
            $last = $db->query("SELECT MAX(CAST(SUBSTR(registro,2) AS INTEGER)) FROM professores")->fetchColumn();
            $reg  = 'P' . str_pad(($last ?? 0)+1, 3, '0', STR_PAD_LEFT);
            $db->prepare("INSERT INTO professores (registro,nome,email,telefone,disciplina,formacao,turmas) VALUES (?,?,?,?,?,?,?)")
               ->execute([$reg,$nome,$email,$tel,$disc,$form,$turmas]);
            logAction(currentId(),'criar','professor',(int)$db->lastInsertId());
            $msg = "✅ Professor <strong>$nome</strong> cadastrado com registro <strong>$reg</strong>.";
        }
    }

    if ($acao === 'editar') {
        $id = (int)($_POST['id']??0);
        $db->prepare("UPDATE professores SET nome=?,email=?,telefone=?,disciplina=?,formacao=?,turmas=?,updated_at=datetime('now') WHERE id=?")
           ->execute([trim($_POST['nome']??''),trim($_POST['email']??''),trim($_POST['telefone']??''),trim($_POST['disciplina']??''),trim($_POST['formacao']??''),trim($_POST['turmas']??''),$id]);
        logAction(currentId(),'editar','professor',$id);
        $msg = "✅ Professor atualizado.";
    }

    if ($acao === 'deletar' && canDelete()) {
        $id = (int)($_POST['id']??0);
        $db->prepare("UPDATE professores SET ativo=0 WHERE id=?")->execute([$id]);
        logAction(currentId(),'deletar','professor',$id);
        $msg = "🗑️ Professor removido.";
    }
}

$search = trim($_GET['q']??'');
$where  = "ativo=1"; $params=[];
if ($search){ $where.=" AND (nome LIKE ? OR registro LIKE ? OR disciplina LIKE ?)"; $params=["%$search%","%$search%","%$search%"]; }
$stmt = $db->prepare("SELECT * FROM professores WHERE $where ORDER BY nome");
$stmt->execute($params);
$profs = $stmt->fetchAll();

layout_start('Professores','professores');
?>

<?php if ($msg):  ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-error">⚠️ <?=htmlspecialchars($erro)?></div><?php endif; ?>

<div class="flex justify-between items-center flex-wrap gap-2 mb-4">
  <div style="font-size:.8rem;color:var(--muted)"><?=count($profs)?> professor(es) encontrado(s)</div>
  <?php if (canEdit()): ?>
  <button class="btn btn-primary" onclick="openModal('modal-criar')">+ Novo Professor</button>
  <?php endif; ?>
</div>

<div class="card mb-4">
  <form method="GET" class="flex gap-2 flex-wrap items-center">
    <input type="text" name="q" class="form-control" placeholder="🔍 Buscar por nome, registro, disciplina..." value="<?=htmlspecialchars($search)?>" style="max-width:320px">
    <button type="submit" class="btn btn-ghost">Filtrar</button>
    <a href="/escola/professores.php" class="btn btn-ghost">Limpar</a>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Registro</th><th>Nome</th><th>Disciplina</th><th>Formação</th><th>Turmas</th><th>Contato</th>
        <?php if(canEdit()): ?><th>Ações</th><?php endif; ?></tr>
      </thead>
      <tbody>
        <?php if ($profs): foreach ($profs as $p): ?>
        <tr>
          <td class="td-code"><?=htmlspecialchars($p['registro'])?></td>
          <td class="td-name"><?=htmlspecialchars($p['nome'])?></td>
          <td><span class="badge badge-info"><?=htmlspecialchars($p['disciplina']??'—')?></span></td>
          <td style="font-size:.78rem"><?=htmlspecialchars($p['formacao']??'—')?></td>
          <td style="font-size:.78rem;font-family:var(--font-mono)"><?=htmlspecialchars($p['turmas']??'—')?></td>
          <td style="font-size:.78rem"><?=htmlspecialchars($p['email']??'—')?></td>
          <?php if(canEdit()): ?>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-ghost btn-xs" onclick='editProf(<?=json_encode($p)?>)'>✏️ Editar</button>
              <?php if(canDelete()): ?>
              <form method="POST" onsubmit="return confirm('Remover este professor?')">
                <input type="hidden" name="acao" value="deletar">
                <input type="hidden" name="id" value="<?=$p['id']?>">
                <button type="submit" class="btn btn-danger btn-xs">🗑️</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted)">Nenhum professor encontrado</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL CRIAR -->
<div class="modal-backdrop" id="modal-criar">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">👨‍🏫 Novo Professor</span>
      <button class="modal-close" onclick="closeModal('modal-criar')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="acao" value="criar">
        <div class="form-grid">
          <div class="form-group"><label>Nome Completo *</label><input type="text" name="nome" class="form-control" required></div>
          <div class="form-group"><label>E-mail</label><input type="email" name="email" class="form-control"></div>
          <div class="form-group"><label>Telefone</label><input type="text" name="telefone" class="form-control"></div>
          <div class="form-group"><label>Disciplina</label><input type="text" name="disciplina" class="form-control" placeholder="Ex: Matemática"></div>
          <div class="form-group" style="grid-column:1/-1"><label>Formação Acadêmica</label><input type="text" name="formacao" class="form-control" placeholder="Ex: Lic. Matemática - USP"></div>
          <div class="form-group" style="grid-column:1/-1"><label>Turmas (separadas por vírgula)</label><input type="text" name="turmas" class="form-control" placeholder="7A,8A,9B"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-criar')">Cancelar</button>
          <button type="submit" class="btn btn-primary">💾 Cadastrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal-backdrop" id="modal-editar">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">✏️ Editar Professor</span>
      <button class="modal-close" onclick="closeModal('modal-editar')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="acao" value="editar">
        <input type="hidden" name="id" id="ep-id">
        <div class="form-grid">
          <div class="form-group"><label>Nome *</label><input type="text" name="nome" id="ep-nome" class="form-control" required></div>
          <div class="form-group"><label>E-mail</label><input type="email" name="email" id="ep-email" class="form-control"></div>
          <div class="form-group"><label>Telefone</label><input type="text" name="telefone" id="ep-tel" class="form-control"></div>
          <div class="form-group"><label>Disciplina</label><input type="text" name="disciplina" id="ep-disc" class="form-control"></div>
          <div class="form-group" style="grid-column:1/-1"><label>Formação</label><input type="text" name="formacao" id="ep-form" class="form-control"></div>
          <div class="form-group" style="grid-column:1/-1"><label>Turmas</label><input type="text" name="turmas" id="ep-turmas" class="form-control"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-editar')">Cancelar</button>
          <button type="submit" class="btn btn-primary">💾 Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editProf(p){
  document.getElementById('ep-id').value    = p.id;
  document.getElementById('ep-nome').value  = p.nome;
  document.getElementById('ep-email').value = p.email||'';
  document.getElementById('ep-tel').value   = p.telefone||'';
  document.getElementById('ep-disc').value  = p.disciplina||'';
  document.getElementById('ep-form').value  = p.formacao||'';
  document.getElementById('ep-turmas').value= p.turmas||'';
  openModal('modal-editar');
}
</script>
<?php layout_end(); ?>
