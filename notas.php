<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db  = getDB();
$msg = ''; $erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'lancar') {
        $aluno_id    = (int)($_POST['aluno_id']??0);
        $disciplina  = trim($_POST['disciplina']??'');
        $bimestre    = (int)($_POST['bimestre']??1);
        $nota        = (float)str_replace(',','.',$_POST['nota']??0);
        $tipo        = trim($_POST['tipo']??'Prova');
        $obs         = trim($_POST['observacao']??'');

        if ($nota < 0 || $nota > 10) { $erro = 'Nota deve ser entre 0 e 10.'; }
        elseif (!$aluno_id || !$disciplina) { $erro = 'Aluno e disciplina são obrigatórios.'; }
        else {
            $db->prepare("INSERT INTO notas (aluno_id,professor_id,disciplina,bimestre,nota,tipo,observacao,data_lancamento) VALUES (?,?,?,?,?,?,?,date('now'))")
               ->execute([$aluno_id, currentId(), $disciplina, $bimestre, $nota, $tipo, $obs]);
            logAction(currentId(),'lancar','nota',(int)$db->lastInsertId());
            $msg = "✅ Nota <strong>$nota</strong> lançada com sucesso.";
        }
    }

    if ($acao === 'editar') {
        $id   = (int)($_POST['id']??0);
        $nota = (float)str_replace(',','.',$_POST['nota']??0);
        $obs  = trim($_POST['observacao']??'');
        if ($nota < 0 || $nota > 10) { $erro = 'Nota inválida.'; }
        else {
            $db->prepare("UPDATE notas SET nota=?,observacao=? WHERE id=?")->execute([$nota,$obs,$id]);
            logAction(currentId(),'editar','nota',$id);
            $msg = "✅ Nota atualizada.";
        }
    }

    if ($acao === 'deletar' && canDelete()) {
        $id = (int)($_POST['id']??0);
        $db->prepare("DELETE FROM notas WHERE id=?")->execute([$id]);
        logAction(currentId(),'deletar','nota',$id);
        $msg = "🗑️ Nota removida.";
    }
}

// Filtros
$fAluno = (int)($_GET['aluno_id']??0);
$fDisc  = trim($_GET['disciplina']??'');
$fBim   = (int)($_GET['bimestre']??0);

$where  = "1=1"; $params = [];
if ($fAluno) { $where .= " AND n.aluno_id=?"; $params[] = $fAluno; }
if ($fDisc)  { $where .= " AND n.disciplina=?"; $params[] = $fDisc; }
if ($fBim)   { $where .= " AND n.bimestre=?"; $params[] = $fBim; }

$notas = $db->prepare("
    SELECT n.*, a.nome as aluno_nome, a.matricula, a.turma, p.nome as prof_nome
    FROM notas n
    JOIN alunos a ON a.id = n.aluno_id
    LEFT JOIN professores p ON p.id = n.professor_id
    WHERE $where ORDER BY n.created_at DESC LIMIT 200
");
$notas->execute($params);
$notas = $notas->fetchAll();

$alunos     = $db->query("SELECT id,nome,matricula FROM alunos WHERE ativo=1 ORDER BY nome")->fetchAll();
$disciplinas = $db->query("SELECT DISTINCT disciplina FROM notas ORDER BY disciplina")->fetchAll(PDO::FETCH_COLUMN);

// Médias por aluno e bimestre
$medias = $db->query("
    SELECT a.nome, a.matricula, n.disciplina,
           AVG(CASE WHEN n.bimestre=1 THEN n.nota END) as b1,
           AVG(CASE WHEN n.bimestre=2 THEN n.nota END) as b2,
           AVG(CASE WHEN n.bimestre=3 THEN n.nota END) as b3,
           AVG(CASE WHEN n.bimestre=4 THEN n.nota END) as b4,
           AVG(n.nota) as media
    FROM notas n JOIN alunos a ON a.id = n.aluno_id
    GROUP BY n.aluno_id, n.disciplina ORDER BY a.nome
")->fetchAll();

function notaClass(float $n): string {
    if ($n >= 8) return 'nota-a';
    if ($n >= 6) return 'nota-b';
    if ($n >= 5) return 'nota-c';
    return 'nota-d';
}

layout_start('Notas','notas');
?>

<?php if ($msg):  ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-error">⚠️ <?=htmlspecialchars($erro)?></div><?php endif; ?>

<div class="tabs">
  <button class="tab active" onclick="switchTab(this,'tab-lancamentos')">📝 Lançamentos</button>
  <button class="tab" onclick="switchTab(this,'tab-boletim')">📋 Boletim</button>
</div>

<!-- ABA LANÇAMENTOS -->
<div id="tab-lancamentos">
  <div class="flex justify-between items-center flex-wrap gap-2 mb-4">
    <div style="font-size:.8rem;color:var(--muted)"><?=count($notas)?> lançamentos</div>
    <button class="btn btn-primary" onclick="openModal('modal-nota')">+ Lançar Nota</button>
  </div>

  <!-- Filtros -->
  <div class="card mb-4">
    <form method="GET" class="flex gap-2 flex-wrap items-center">
      <select name="aluno_id" class="form-control" style="max-width:200px">
        <option value="">Todos os alunos</option>
        <?php foreach ($alunos as $a): ?><option value="<?=$a['id']?>" <?=$fAluno==$a['id']?'selected':''?>><?=htmlspecialchars($a['nome'])?></option><?php endforeach; ?>
      </select>
      <select name="disciplina" class="form-control" style="max-width:160px">
        <option value="">Disciplina</option>
        <?php foreach ($disciplinas as $d): ?><option value="<?=htmlspecialchars($d)?>" <?=$fDisc===$d?'selected':''?>><?=$d?></option><?php endforeach; ?>
      </select>
      <select name="bimestre" class="form-control" style="max-width:130px">
        <option value="">Bimestre</option>
        <?php for($i=1;$i<=4;$i++): ?><option value="<?=$i?>" <?=$fBim===$i?'selected':''?>><?=$i?>º Bimestre</option><?php endfor; ?>
      </select>
      <button type="submit" class="btn btn-ghost">Filtrar</button>
      <a href="/escola/notas.php" class="btn btn-ghost">Limpar</a>
    </form>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Aluno</th><th>Matrícula</th><th>Disciplina</th><th>Bimestre</th><th>Nota</th><th>Tipo</th><th>Data</th><th>Ações</th></tr></thead>
        <tbody>
          <?php if ($notas): foreach ($notas as $n): ?>
          <tr>
            <td class="td-name"><?=htmlspecialchars($n['aluno_nome'])?></td>
            <td class="td-code"><?=htmlspecialchars($n['matricula'])?></td>
            <td><?=htmlspecialchars($n['disciplina'])?></td>
            <td style="text-align:center"><?=$n['bimestre']?>º</td>
            <td style="text-align:center"><span class="nota-chip <?=notaClass($n['nota'])?>"><?=number_format($n['nota'],1)?></span></td>
            <td><span class="badge badge-info"><?=htmlspecialchars($n['tipo'])?></span></td>
            <td class="td-code"><?=htmlspecialchars($n['data_lancamento'])?></td>
            <td>
              <div class="flex gap-2">
                <button class="btn btn-ghost btn-xs" onclick='editNota(<?=json_encode($n)?>)'>✏️</button>
                <?php if(canDelete()): ?>
                <form method="POST" onsubmit="return confirm('Remover nota?')">
                  <input type="hidden" name="acao" value="deletar">
                  <input type="hidden" name="id" value="<?=$n['id']?>">
                  <button type="submit" class="btn btn-danger btn-xs">🗑️</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--muted)">Nenhuma nota encontrada</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ABA BOLETIM -->
<div id="tab-boletim" style="display:none">
  <div class="card">
    <div class="card-header"><span class="card-title">📋 Boletim Geral por Disciplina</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Aluno</th><th>Matrícula</th><th>Disciplina</th><th>1º Bim</th><th>2º Bim</th><th>3º Bim</th><th>4º Bim</th><th>Média</th><th>Situação</th></tr></thead>
        <tbody>
          <?php foreach ($medias as $m):
            $media = (float)$m['media'];
            $sit   = $media >= 6 ? ['Aprovado','badge-success'] : ($media >= 5 ? ['Recuperação','badge-warn'] : ['Reprovado','badge-danger']);
          ?>
          <tr>
            <td class="td-name"><?=htmlspecialchars($m['nome'])?></td>
            <td class="td-code"><?=htmlspecialchars($m['matricula'])?></td>
            <td><?=htmlspecialchars($m['disciplina'])?></td>
            <?php foreach(['b1','b2','b3','b4'] as $b): $v=$m[$b]; ?>
            <td style="text-align:center"><?=$v!==null?'<span class="nota-chip '.notaClass((float)$v).'">'.number_format($v,1).'</span>':'—'?></td>
            <?php endforeach; ?>
            <td style="text-align:center;font-weight:700"><?=number_format($media,1)?></td>
            <td><span class="badge <?=$sit[1]?>"><?=$sit[0]?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL LANÇAR NOTA -->
<div class="modal-backdrop" id="modal-nota">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">📝 Lançar Nota</span>
      <button class="modal-close" onclick="closeModal('modal-nota')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="acao" value="lancar">
        <div class="form-grid">
          <div class="form-group" style="grid-column:1/-1">
            <label>Aluno *</label>
            <select name="aluno_id" class="form-control" required>
              <option value="">— Selecione —</option>
              <?php foreach ($alunos as $a): ?><option value="<?=$a['id']?>"><?=htmlspecialchars($a['nome'])?> (<?=$a['matricula']?>)</option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Disciplina *</label><input type="text" name="disciplina" class="form-control" placeholder="Ex: Matemática" required></div>
          <div class="form-group"><label>Nota (0–10) *</label><input type="number" name="nota" class="form-control" step="0.1" min="0" max="10" placeholder="7.5" required></div>
          <div class="form-group"><label>Bimestre</label>
            <select name="bimestre" class="form-control">
              <?php for($i=1;$i<=4;$i++): ?><option value="<?=$i?>"><?=$i?>º Bimestre</option><?php endfor; ?>
            </select>
          </div>
          <div class="form-group"><label>Tipo de avaliação</label>
            <select name="tipo" class="form-control">
              <?php foreach(['Prova','Trabalho','Atividade','Projeto','Recuperação'] as $t): ?><option><?=$t?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="grid-column:1/-1"><label>Observação</label><textarea name="observacao" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-nota')">Cancelar</button>
          <button type="submit" class="btn btn-primary">💾 Lançar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EDITAR NOTA -->
<div class="modal-backdrop" id="modal-edit-nota">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">✏️ Editar Nota</span>
      <button class="modal-close" onclick="closeModal('modal-edit-nota')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="acao" value="editar">
        <input type="hidden" name="id" id="en-id">
        <div class="form-grid">
          <div class="form-group"><label>Nota (0–10)</label><input type="number" name="nota" id="en-nota" class="form-control" step="0.1" min="0" max="10"></div>
          <div class="form-group" style="grid-column:1/-1"><label>Observação</label><textarea name="observacao" id="en-obs" class="form-control"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-nota')">Cancelar</button>
          <button type="submit" class="btn btn-primary">💾 Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editNota(n){
  document.getElementById('en-id').value   = n.id;
  document.getElementById('en-nota').value = n.nota;
  document.getElementById('en-obs').value  = n.observacao||'';
  openModal('modal-edit-nota');
}
</script>
<?php layout_end(); ?>
