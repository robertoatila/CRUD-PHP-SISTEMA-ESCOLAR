<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db  = getDB();
$msg = '';
$erro = '';

// ── Ações POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && canEdit()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar' || $acao === 'editar') {
        $fields = ['nome','email','telefone','data_nascimento','turma','serie','turno','responsavel','tel_responsavel','endereco','observacoes'];
        $data   = [];
        foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');

        if (!$data['nome']) { $erro = 'Nome é obrigatório.'; }
        elseif ($acao === 'criar') {
            // Gera matrícula automática
            $ano  = date('Y');
            $last = $db->query("SELECT MAX(CAST(SUBSTR(matricula,5) AS INTEGER)) FROM alunos WHERE matricula LIKE '$ano%'")->fetchColumn();
            $num  = str_pad(($last ?? 0) + 1, 3, '0', STR_PAD_LEFT);
            $mat  = $ano . $num;
            $stmt = $db->prepare("INSERT INTO alunos (matricula,nome,email,telefone,data_nascimento,turma,serie,turno,responsavel,tel_responsavel,endereco,observacoes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$mat,$data['nome'],$data['email'],$data['telefone'],$data['data_nascimento'],$data['turma'],$data['serie'],$data['turno'],$data['responsavel'],$data['tel_responsavel'],$data['endereco'],$data['observacoes']]);
            logAction(currentId(),'criar','aluno',(int)$db->lastInsertId());
            $msg = "✅ Aluno <strong>{$data['nome']}</strong> cadastrado com matrícula <strong>$mat</strong>.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $set = implode(',', array_map(fn($f) => "$f=?", $fields));
            $vals = array_values($data); $vals[] = $id;
            $db->prepare("UPDATE alunos SET $set, updated_at=datetime('now') WHERE id=?")->execute($vals);
            logAction(currentId(),'editar','aluno',$id);
            $msg = "✅ Dados do aluno atualizados.";
        }
    }

    if ($acao === 'deletar' && canDelete()) {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE alunos SET ativo=0 WHERE id=?")->execute([$id]);
        logAction(currentId(),'deletar','aluno',$id);
        $msg = "🗑️ Aluno removido.";
    }
}

// ── Filtros ──────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$turma  = trim($_GET['turma'] ?? '');
$serie  = trim($_GET['serie'] ?? '');
$where  = "ativo=1";
$params = [];
if ($search) { $where .= " AND (nome LIKE ? OR matricula LIKE ? OR email LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($turma)  { $where .= " AND turma=?"; $params[] = $turma; }
if ($serie)  { $where .= " AND serie=?"; $params[] = $serie; }

$stmt = $db->prepare("SELECT * FROM alunos WHERE $where ORDER BY nome");
$stmt->execute($params);
$alunos = $stmt->fetchAll();

$turmas = $db->query("SELECT DISTINCT turma FROM alunos WHERE ativo=1 ORDER BY turma")->fetchAll(PDO::FETCH_COLUMN);
$series = $db->query("SELECT DISTINCT serie FROM alunos WHERE ativo=1 ORDER BY serie")->fetchAll(PDO::FETCH_COLUMN);

layout_start('Alunos', 'alunos');
?>

<?php if ($msg):  ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-error">⚠️ <?=htmlspecialchars($erro)?></div><?php endif; ?>

<!-- HEADER -->
<div class="flex justify-between items-center flex-wrap gap-2 mb-4">
  <div>
    <div style="font-size:.8rem;color:var(--muted)"><?=count($alunos)?> aluno(s) encontrado(s)</div>
  </div>
  <div class="flex gap-2 flex-wrap">
    <?php if (canEdit()): ?>
    <button class="btn btn-primary" onclick="openModal('modal-criar')">+ Novo Aluno</button>
    <?php endif; ?>
  </div>
</div>

<!-- FILTROS -->
<div class="card mb-4">
  <form method="GET" class="flex gap-2 flex-wrap items-center">
    <input type="text" name="q" class="form-control" placeholder="🔍 Buscar por nome, matrícula..." value="<?=htmlspecialchars($search)?>" style="max-width:260px">
    <select name="turma" class="form-control" style="max-width:120px">
      <option value="">Turma</option>
      <?php foreach ($turmas as $t): ?><option value="<?=htmlspecialchars($t)?>" <?=$turma===$t?'selected':''?>><?=$t?></option><?php endforeach; ?>
    </select>
    <select name="serie" class="form-control" style="max-width:140px">
      <option value="">Série</option>
      <?php foreach ($series as $s): ?><option value="<?=htmlspecialchars($s)?>" <?=$serie===$s?'selected':''?>><?=$s?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-ghost">Filtrar</button>
    <a href="/escola/alunos.php" class="btn btn-ghost">Limpar</a>
  </form>
</div>

<!-- TABELA -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Matrícula</th><th>Nome</th><th>Turma</th><th>Série</th>
          <th>Turno</th><th>Responsável</th><th>Status</th>
          <?php if (canEdit()): ?><th>Ações</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if ($alunos): foreach ($alunos as $a): ?>
        <tr>
          <td class="td-code"><?=htmlspecialchars($a['matricula'])?></td>
          <td class="td-name"><?=htmlspecialchars($a['nome'])?></td>
          <td><?=htmlspecialchars($a['turma'])?></td>
          <td><?=htmlspecialchars($a['serie'])?></td>
          <td><span class="badge badge-info"><?=htmlspecialchars($a['turno'])?></span></td>
          <td style="font-size:.8rem"><?=htmlspecialchars($a['responsavel']??'—')?></td>
          <td><span class="badge badge-success">Ativo</span></td>
          <?php if (canEdit()): ?>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-ghost btn-xs" onclick='editAluno(<?=json_encode($a)?>)'>✏️ Editar</button>
              <?php if (canDelete()): ?>
              <form method="POST" onsubmit="return confirm('Remover este aluno?')">
                <input type="hidden" name="acao" value="deletar">
                <input type="hidden" name="id" value="<?=$a['id']?>">
                <button type="submit" class="btn btn-danger btn-xs">🗑️</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--muted)">Nenhum aluno encontrado</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL CRIAR -->
<div class="modal-backdrop" id="modal-criar">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">👨‍🎓 Novo Aluno</span>
      <button class="modal-close" onclick="closeModal('modal-criar')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="acao" value="criar">
        <div class="form-grid">
          <div class="form-group"><label>Nome Completo *</label><input type="text" name="nome" class="form-control" required></div>
          <div class="form-group"><label>E-mail</label><input type="email" name="email" class="form-control"></div>
          <div class="form-group"><label>Telefone</label><input type="text" name="telefone" class="form-control" placeholder="(11) 9999-0000"></div>
          <div class="form-group"><label>Data de Nascimento</label><input type="date" name="data_nascimento" class="form-control"></div>
          <div class="form-group"><label>Turma</label><input type="text" name="turma" class="form-control" placeholder="A, B, C..."></div>
          <div class="form-group"><label>Série</label>
            <select name="serie" class="form-control">
              <?php foreach (['6º Ano','7º Ano','8º Ano','9º Ano','1º EM','2º EM','3º EM'] as $s): ?><option><?=$s?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Turno</label>
            <select name="turno" class="form-control"><option>Matutino</option><option>Vespertino</option><option>Noturno</option></select>
          </div>
          <div class="form-group"><label>Responsável</label><input type="text" name="responsavel" class="form-control"></div>
          <div class="form-group"><label>Tel. Responsável</label><input type="text" name="tel_responsavel" class="form-control"></div>
          <div class="form-group" style="grid-column:1/-1"><label>Endereço</label><input type="text" name="endereco" class="form-control"></div>
          <div class="form-group" style="grid-column:1/-1"><label>Observações</label><textarea name="observacoes" class="form-control"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-criar')">Cancelar</button>
          <button type="submit" class="btn btn-primary">💾 Cadastrar Aluno</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal-backdrop" id="modal-editar">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">✏️ Editar Aluno</span>
      <button class="modal-close" onclick="closeModal('modal-editar')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="form-editar">
        <input type="hidden" name="acao" value="editar">
        <input type="hidden" name="id" id="edit-id">
        <div class="form-grid">
          <div class="form-group"><label>Nome Completo *</label><input type="text" name="nome" id="edit-nome" class="form-control" required></div>
          <div class="form-group"><label>E-mail</label><input type="email" name="email" id="edit-email" class="form-control"></div>
          <div class="form-group"><label>Telefone</label><input type="text" name="telefone" id="edit-telefone" class="form-control"></div>
          <div class="form-group"><label>Data de Nascimento</label><input type="date" name="data_nascimento" id="edit-nasc" class="form-control"></div>
          <div class="form-group"><label>Turma</label><input type="text" name="turma" id="edit-turma" class="form-control"></div>
          <div class="form-group"><label>Série</label>
            <select name="serie" id="edit-serie" class="form-control">
              <?php foreach (['6º Ano','7º Ano','8º Ano','9º Ano','1º EM','2º EM','3º EM'] as $s): ?><option><?=$s?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Turno</label>
            <select name="turno" id="edit-turno" class="form-control"><option>Matutino</option><option>Vespertino</option><option>Noturno</option></select>
          </div>
          <div class="form-group"><label>Responsável</label><input type="text" name="responsavel" id="edit-resp" class="form-control"></div>
          <div class="form-group"><label>Tel. Responsável</label><input type="text" name="tel_responsavel" id="edit-telresp" class="form-control"></div>
          <div class="form-group" style="grid-column:1/-1"><label>Endereço</label><input type="text" name="endereco" id="edit-end" class="form-control"></div>
          <div class="form-group" style="grid-column:1/-1"><label>Observações</label><textarea name="observacoes" id="edit-obs" class="form-control"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-editar')">Cancelar</button>
          <button type="submit" class="btn btn-primary">💾 Salvar Alterações</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editAluno(a){
  document.getElementById('edit-id').value      = a.id;
  document.getElementById('edit-nome').value    = a.nome;
  document.getElementById('edit-email').value   = a.email||'';
  document.getElementById('edit-telefone').value= a.telefone||'';
  document.getElementById('edit-nasc').value    = a.data_nascimento||'';
  document.getElementById('edit-turma').value   = a.turma||'';
  document.getElementById('edit-serie').value   = a.serie||'';
  document.getElementById('edit-turno').value   = a.turno||'Matutino';
  document.getElementById('edit-resp').value    = a.responsavel||'';
  document.getElementById('edit-telresp').value = a.tel_responsavel||'';
  document.getElementById('edit-end').value     = a.endereco||'';
  document.getElementById('edit-obs').value     = a.observacoes||'';
  openModal('modal-editar');
}
</script>

<?php layout_end(); ?>
