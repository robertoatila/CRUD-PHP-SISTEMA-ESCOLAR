<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db = getDB();

$totalAlunos   = $db->query("SELECT COUNT(*) FROM alunos   WHERE ativo=1")->fetchColumn();
$totalProfs    = $db->query("SELECT COUNT(*) FROM professores WHERE ativo=1")->fetchColumn();
$totalUsuarios = $db->query("SELECT COUNT(*) FROM usuarios WHERE ativo=1")->fetchColumn();

// Média geral de notas
$mediaGeral = round((float)$db->query("SELECT AVG(nota) FROM notas")->fetchColumn(), 1);

// Presenças da semana (últimos 7 dias)
$presStmt = $db->query("
    SELECT status, COUNT(*) as cnt FROM presenca
    WHERE data >= date('now','-7 days')
    GROUP BY status
");
$presData = ['Presente'=>0,'Ausente'=>0,'Justificado'=>0];
foreach ($presStmt->fetchAll() as $r) $presData[$r['status']] = $r['cnt'];
$totalPres = array_sum($presData);
$pctPres = $totalPres ? round($presData['Presente']/$totalPres*100) : 0;

// Notas por disciplina
$discNotas = $db->query("
    SELECT disciplina, AVG(nota) as media, COUNT(*) as cnt
    FROM notas GROUP BY disciplina ORDER BY media DESC LIMIT 6
")->fetchAll();

// Últimos logs
$logs = $db->query("
    SELECT l.*, u.nome, u.cargo FROM logs l
    LEFT JOIN usuarios u ON u.id = l.usuario_id
    ORDER BY l.created_at DESC LIMIT 8
")->fetchAll();

// Alunos recentes
$alunosRecentes = $db->query("SELECT * FROM alunos ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Relatórios gerados
$totalRelatorios = $db->query("SELECT COUNT(*) FROM relatorios")->fetchColumn();

layout_start('Dashboard', 'dashboard');
?>

<!-- STATS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(16,185,129,.12)">👨‍🎓</div>
    <div class="stat-info">
      <div class="value"><?=$totalAlunos?></div>
      <div class="label">Alunos Ativos</div>
      <div class="change">↑ Matriculados</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(99,102,241,.12)">👨‍🏫</div>
    <div class="stat-info">
      <div class="value"><?=$totalProfs?></div>
      <div class="label">Professores</div>
      <div class="change">Corpo docente</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(240,165,0,.12)">📝</div>
    <div class="stat-info">
      <div class="value"><?=$mediaGeral ?: '—'?></div>
      <div class="label">Média Geral</div>
      <div class="change">Todas disciplinas</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(59,130,246,.12)">📅</div>
    <div class="stat-info">
      <div class="value"><?=$pctPres?>%</div>
      <div class="label">Presença Semanal</div>
      <div class="change"><?=$presData['Ausente']?> ausências</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(244,63,94,.12)">📊</div>
    <div class="stat-info">
      <div class="value"><?=$totalRelatorios?></div>
      <div class="label">Relatórios</div>
      <div class="change">Gerados no sistema</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(240,165,0,.12)">👥</div>
    <div class="stat-info">
      <div class="value"><?=$totalUsuarios?></div>
      <div class="label">Usuários</div>
      <div class="change">4 níveis de acesso</div>
    </div>
  </div>
</div>

<!-- ROW 2 -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">

  <!-- Médias por disciplina -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📊 Médias por Disciplina</span>
    </div>
    <?php if ($discNotas): ?>
    <div class="bar-chart">
      <?php foreach ($discNotas as $d):
        $pct = $d['media']/10*100;
        $color = $d['media']>=7?'var(--emerald)':($d['media']>=5?'var(--gold)':'var(--rose)');
      ?>
      <div class="bar-row">
        <span class="bar-label"><?=htmlspecialchars(mb_substr($d['disciplina'],0,10))?></span>
        <div class="bar-track"><div class="bar-fill" style="width:<?=$pct?>%;background:<?=$color?>"></div></div>
        <span class="bar-val"><?=number_format($d['media'],1)?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="icon">📝</div><p>Nenhuma nota lançada ainda.</p></div>
    <?php endif; ?>
  </div>

  <!-- Presença semanal -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📅 Presença — Últimos 7 dias</span>
    </div>
    <?php
    $colors = ['Presente'=>'var(--emerald)','Ausente'=>'var(--rose)','Justificado'=>'var(--gold)'];
    foreach ($presData as $st => $cnt):
      $pct = $totalPres ? $cnt/$totalPres*100 : 0;
    ?>
    <div class="bar-row" style="margin-bottom:.6rem">
      <span class="bar-label"><?=$st?></span>
      <div class="bar-track"><div class="bar-fill" style="width:<?=$pct?>%;background:<?=$colors[$st]?>"></div></div>
      <span class="bar-val"><?=$cnt?></span>
    </div>
    <?php endforeach; ?>

    <hr class="sep">
    <div style="font-size:.8rem;color:var(--muted)">
      Total de registros: <strong style="color:var(--text)"><?=$totalPres?></strong>
      &nbsp;·&nbsp; Taxa de presença: <strong style="color:var(--emerald)"><?=$pctPres?>%</strong>
    </div>
  </div>
</div>

<!-- ROW 3 -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">

  <!-- Alunos recentes -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">👨‍🎓 Alunos Recentes</span>
      <a href="/escola/alunos.php" class="btn btn-ghost btn-sm">Ver todos →</a>
    </div>
    <?php if ($alunosRecentes): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Matrícula</th><th>Nome</th><th>Turma</th><th>Série</th></tr></thead>
        <tbody>
          <?php foreach ($alunosRecentes as $a): ?>
          <tr>
            <td class="td-code"><?=htmlspecialchars($a['matricula'])?></td>
            <td class="td-name"><?=htmlspecialchars($a['nome'])?></td>
            <td><?=htmlspecialchars($a['turma'])?></td>
            <td><?=htmlspecialchars($a['serie'])?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="icon">👨‍🎓</div><p>Nenhum aluno cadastrado.</p></div>
    <?php endif; ?>
  </div>

  <!-- Logs recentes -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🕒 Atividade Recente</span>
    </div>
    <?php if ($logs): ?>
    <div style="display:flex;flex-direction:column;gap:.5rem">
      <?php foreach ($logs as $l): global $ROLE_COLORS;
        $c = $ROLE_COLORS[(int)$l['cargo']] ?? '#888';
      ?>
      <div style="display:flex;align-items:center;gap:.7rem;padding:.5rem;background:var(--bg3);border-radius:6px;font-size:.78rem">
        <div class="avatar" style="width:26px;height:26px;font-size:.65rem;background:<?=$c?>"><?=mb_strtoupper(mb_substr($l['nome']??'?',0,1))?></div>
        <div style="flex:1">
          <strong><?=htmlspecialchars($l['nome']??'Sistema')?></strong>
          <span style="color:var(--muted)"> · <?=htmlspecialchars($l['acao'])?> <?=htmlspecialchars($l['entidade'])?></span>
        </div>
        <span style="color:var(--muted);font-size:.7rem;white-space:nowrap"><?=substr($l['created_at'],11,5)?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="icon">🕒</div><p>Nenhuma atividade registrada.</p></div>
    <?php endif; ?>
  </div>
</div>

<?php layout_end(); ?>
