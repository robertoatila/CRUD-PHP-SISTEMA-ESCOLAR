<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db = getDB();
$msg = ''; $erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'registrar_chamada') {
        $disciplina = trim($_POST['disciplina']??'');
        $data       = trim($_POST['data']??'');
        $presencas  = $_POST['presenca'] ?? [];

        if (!$disciplina || !$data) { $erro = 'Disciplina e data são obrigatórios.'; }
        else {
            // Remove registros anteriores do mesmo dia/disciplina
            $db->prepare("DELETE FROM presenca WHERE disciplina=? AND data=?")->execute([$disciplina,$data]);

            $stmt = $db->prepare("INSERT INTO presenca (aluno_id,professor_id,disciplina,data,status,observacao) VALUES (?,?,?,?,?,?)");
            foreach ($presencas as $alunoId => $status) {
                $obs = trim($_POST['obs'][$alunoId] ?? '');
                $stmt->execute([(int)$alunoId, currentId(), $disciplina, $data, $status, $obs]);
            }
            logAction(currentId(),'chamada','presenca',0);
            $msg = "✅ Chamada registrada para <strong>$disciplina</strong> em <strong>$data</strong>.";
        }
    }
}

// Histórico
$fAluno = (int)($_GET['aluno_id']??0);
$fDisc  = trim($_GET['disciplina']??'');
$fData  = trim($_GET['data']??'');

$where = "1=1"; $params = [];
if ($fAluno) { $where .= " AND p.aluno_id=?"; $params[] = $fAluno; }
if ($fDisc)  { $where .= " AND p.disciplina=?"; $params[] = $fDisc; }
if ($fData)  { $where .= " AND p.data=?"; $params[] = $fData; }

$historico = $db->prepare("
    SELECT p.*, a.nome as aluno_nome, a.matricula, a.turma
    FROM presenca p JOIN alunos a ON a.id = p.aluno_id
    WHERE $where ORDER BY p.data DESC, a.nome LIMIT 300
");
$historico->execute($params);
$historico = $historico->fetchAll();

// Estatísticas por aluno
$stats = $db->query("
    SELECT a.nome, a.matricula, a.turma,
        COUNT(*) as total,
        SUM(CASE WHEN p.status='Presente' THEN 1 ELSE 0 END) as presentes,
        SUM(CASE WHEN p.status='Ausente' THEN 1 ELSE 0 END) as ausentes,
        SUM(CASE WHEN p.status='Justificado' THEN 1 ELSE 0 END) as justificados
    FROM presenca p JOIN alunos a ON a.id = p.aluno_id
    GROUP BY p.aluno_id ORDER BY a.nome
")->fetchAll();

$alunos = $db->query("SELECT id,nome,matricula,turma FROM alunos WHERE ativo=1 ORDER BY nome")->fetchAll();
$discs  = $db->query("SELECT DISTINCT disciplina FROM presenca ORDER BY disciplina")->fetchAll(PDO::FETCH_COLUMN);

layout_start('Presença','presenca');
?>

<?php if ($msg):  ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-error">⚠️ <?=htmlspecialchars($erro)?></div><?php endif; ?>

<div class="tabs">
  <button class="tab active" onclick="switchTab(this,'tab-chamada')">📋 Fazer Chamada</button>
  <button class="tab" onclick="switchTab(this,'tab-historico')">🕒 Histórico</button>
  <button class="tab" onclick="switchTab(this,'tab-stats')">📊 Frequência</button>
</div>

<!-- ABA CHAMADA -->
<div id="tab-chamada">
  <div class="card mb-4">
    <div class="card-header"><span class="card-title">⚙️ Configurar Chamada</span></div>
    <div class="form-grid">
      <div class="form-group">
        <label>Disciplina</label>
        <input type="text" id="cfg-disc" class="form-control" placeholder="Ex: Matemática">
      </div>
      <div class="form-group">
        <label>Data</label>
        <input type="date" id="cfg-data" class="form-control" value="<?=date('Y-m-d')?>">
      </div>
      <div class="form-group">
        <label>Turma (opcional)</label>
        <select id="cfg-turma" class="form-control">
          <option value="">Todas as turmas</option>
          <?php
            $turmas = $db->query("SELECT DISTINCT turma FROM alunos WHERE ativo=1 ORDER BY turma")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($turmas as $t): ?>
          <option value="<?=htmlspecialchars($t)?>"><?=$t?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="align-self:flex-end">
        <button class="btn btn-primary w-full" onclick="carregarChamada()">📋 Carregar Alunos</button>
      </div>
    </div>
  </div>

  <div id="area-chamada" style="display:none">
    <form method="POST" id="form-chamada">
      <input type="hidden" name="acao" value="registrar_chamada">
      <input type="hidden" name="disciplina" id="fc-disc">
      <input type="hidden" name="data" id="fc-data">

      <div class="card">
        <div class="card-header">
          <span class="card-title">👨‍🎓 Lista de Chamada</span>
          <div class="flex gap-2">
            <button type="button" class="btn btn-ghost btn-sm" onclick="marcarTodos('Presente')">✅ Todos Presentes</button>
            <button type="button" class="btn btn-danger btn-sm" onclick="marcarTodos('Ausente')">❌ Todos Ausentes</button>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>#</th><th>Matrícula</th><th>Nome</th><th>Turma</th><th>Status</th><th>Obs.</th></tr></thead>
            <tbody id="tbody-chamada"></tbody>
          </table>
        </div>
        <div style="padding:1rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
          <button type="submit" class="btn btn-primary">💾 Salvar Chamada</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ABA HISTÓRICO -->
<div id="tab-historico" style="display:none">
  <div class="card mb-4">
    <form method="GET" class="flex gap-2 flex-wrap items-center">
      <select name="aluno_id" class="form-control" style="max-width:200px">
        <option value="">Todos os alunos</option>
        <?php foreach ($alunos as $a): ?><option value="<?=$a['id']?>" <?=$fAluno==$a['id']?'selected':''?>><?=htmlspecialchars($a['nome'])?></option><?php endforeach; ?>
      </select>
      <select name="disciplina" class="form-control" style="max-width:160px">
        <option value="">Disciplina</option>
        <?php foreach ($discs as $d): ?><option value="<?=htmlspecialchars($d)?>" <?=$fDisc===$d?'selected':''?>><?=$d?></option><?php endforeach; ?>
      </select>
      <input type="date" name="data" class="form-control" value="<?=htmlspecialchars($fData)?>" style="max-width:160px">
      <button type="submit" class="btn btn-ghost">Filtrar</button>
      <a href="/escola/presenca.php" class="btn btn-ghost">Limpar</a>
    </form>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Data</th><th>Aluno</th><th>Matrícula</th><th>Turma</th><th>Disciplina</th><th>Status</th><th>Observação</th></tr></thead>
        <tbody>
          <?php foreach ($historico as $h):
            $dot = $h['status']==='Presente'?'dot-p':($h['status']==='Ausente'?'dot-a':'dot-j');
          ?>
          <tr>
            <td class="td-code"><?=date('d/m/Y',strtotime($h['data']))?></td>
            <td class="td-name"><?=htmlspecialchars($h['aluno_nome'])?></td>
            <td class="td-code"><?=htmlspecialchars($h['matricula'])?></td>
            <td><?=htmlspecialchars($h['turma'])?></td>
            <td><?=htmlspecialchars($h['disciplina'])?></td>
            <td><span class="dot <?=$dot?>"></span> <?=htmlspecialchars($h['status'])?></td>
            <td style="font-size:.78rem;color:var(--muted)"><?=htmlspecialchars($h['observacao']??'')?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$historico): ?>
          <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted)">Nenhum registro encontrado</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ABA FREQUÊNCIA -->
<div id="tab-stats" style="display:none">
  <div class="card">
    <div class="card-header"><span class="card-title">📊 Frequência por Aluno</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Aluno</th><th>Matrícula</th><th>Turma</th><th>Total Aulas</th><th>Presentes</th><th>Ausentes</th><th>Justificados</th><th>Frequência</th><th>Situação</th></tr></thead>
        <tbody>
          <?php foreach ($stats as $s):
            $freq = $s['total'] ? round($s['presentes']/$s['total']*100) : 0;
            $sit  = $freq >= 75 ? ['Regular','badge-success'] : ['Atenção','badge-danger'];
          ?>
          <tr>
            <td class="td-name"><?=htmlspecialchars($s['nome'])?></td>
            <td class="td-code"><?=htmlspecialchars($s['matricula'])?></td>
            <td><?=htmlspecialchars($s['turma'])?></td>
            <td style="text-align:center"><?=$s['total']?></td>
            <td style="text-align:center;color:var(--emerald)"><?=$s['presentes']?></td>
            <td style="text-align:center;color:var(--rose)"><?=$s['ausentes']?></td>
            <td style="text-align:center;color:var(--gold)"><?=$s['justificados']?></td>
            <td>
              <div class="bar-track" style="width:100px;display:inline-block">
                <div class="bar-fill" style="width:<?=$freq?>%;background:<?=$freq>=75?'var(--emerald)':'var(--rose)'?>"></div>
              </div>
              <span style="font-family:var(--font-mono);font-size:.75rem;margin-left:.4rem"><?=$freq?>%</span>
            </td>
            <td><span class="badge <?=$sit[1]?>"><?=$sit[0]?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$stats): ?>
          <tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--muted)">Nenhum dado disponível</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
const todosAlunos = <?=json_encode($alunos)?>;

function carregarChamada(){
  const disc  = document.getElementById('cfg-disc').value.trim();
  const data  = document.getElementById('cfg-data').value;
  const turma = document.getElementById('cfg-turma').value;

  if (!disc||!data){ alert('Preencha disciplina e data.'); return; }

  document.getElementById('fc-disc').value = disc;
  document.getElementById('fc-data').value  = data;

  let lista = todosAlunos.filter(a => !turma || a.turma === turma);
  const tbody = document.getElementById('tbody-chamada');
  tbody.innerHTML = lista.map((a,i) => `
    <tr>
      <td>${i+1}</td>
      <td class="td-code">${a.matricula}</td>
      <td class="td-name">${a.nome}</td>
      <td>${a.turma}</td>
      <td>
        <label style="margin-right:.5rem;font-size:.8rem;cursor:pointer">
          <input type="radio" name="presenca[${a.id}]" value="Presente" checked> ✅ Presente
        </label>
        <label style="margin-right:.5rem;font-size:.8rem;cursor:pointer">
          <input type="radio" name="presenca[${a.id}]" value="Ausente"> ❌ Ausente
        </label>
        <label style="font-size:.8rem;cursor:pointer">
          <input type="radio" name="presenca[${a.id}]" value="Justificado"> 📋 Justif.
        </label>
      </td>
      <td><input type="text" name="obs[${a.id}]" class="form-control" placeholder="Obs..." style="max-width:160px"></td>
    </tr>
  `).join('');

  document.getElementById('area-chamada').style.display = lista.length ? '' : 'none';
  if (!lista.length) alert('Nenhum aluno encontrado para esta turma.');
}

function marcarTodos(status){
  document.querySelectorAll(`input[type=radio][value="${status}"]`).forEach(r=>r.checked=true);
}
</script>

<?php layout_end(); ?>
