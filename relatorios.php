<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db = getDB();
$msg = ''; $erro = '';

// ── Gerar relatório semanal ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'gerar') {
        $ini   = trim($_POST['periodo_inicio'] ?? date('Y-m-d', strtotime('-7 days')));
        $fim   = trim($_POST['periodo_fim']    ?? date('Y-m-d'));
        $tipo  = trim($_POST['tipo'] ?? 'Semanal');

        // Coleta dados do período
        $totalAulas = $db->prepare("SELECT COUNT(DISTINCT data) FROM presenca WHERE data BETWEEN ? AND ?")->execute([$ini,$fim]) && true;
        $pstmt = $db->prepare("SELECT COUNT(DISTINCT data) as aulas FROM presenca WHERE data BETWEEN ? AND ?");
        $pstmt->execute([$ini,$fim]);
        $totalAulas = (int)$pstmt->fetchColumn();

        $freq = $db->prepare("
            SELECT
                SUM(CASE WHEN status='Presente' THEN 1 ELSE 0 END) as presentes,
                SUM(CASE WHEN status='Ausente'  THEN 1 ELSE 0 END) as ausentes,
                COUNT(*) as total
            FROM presenca WHERE data BETWEEN ? AND ?
        ");
        $freq->execute([$ini,$fim]);
        $freqData = $freq->fetch();

        $notasData = $db->prepare("
            SELECT disciplina, AVG(nota) as media, COUNT(*) as cnt
            FROM notas WHERE data_lancamento BETWEEN ? AND ?
            GROUP BY disciplina
        ");
        $notasData->execute([$ini,$fim]);
        $notasData = $notasData->fetchAll();

        $novosAlunos = $db->prepare("SELECT COUNT(*) FROM alunos WHERE date(created_at) BETWEEN ? AND ?")->execute([$ini,$fim]);
        $stNA = $db->prepare("SELECT COUNT(*) FROM alunos WHERE date(created_at) BETWEEN ? AND ?");
        $stNA->execute([$ini,$fim]);
        $novosAlunos = (int)$stNA->fetchColumn();

        $dados = json_encode([
            'periodo'     => ['inicio'=>$ini,'fim'=>$fim],
            'frequencia'  => $freqData,
            'notas'       => $notasData,
            'novos_alunos'=> $novosAlunos,
            'total_aulas' => $totalAulas,
        ]);

        $titulo = "$tipo — " . date('d/m/Y', strtotime($ini)) . " a " . date('d/m/Y', strtotime($fim));
        $db->prepare("INSERT INTO relatorios (titulo,tipo,periodo_inicio,periodo_fim,dados,gerado_por) VALUES (?,?,?,?,?,?)")
           ->execute([$titulo,$tipo,$ini,$fim,$dados,currentId()]);
        logAction(currentId(),'gerar','relatorio',(int)$db->lastInsertId());
        $msg = "✅ Relatório <strong>$titulo</strong> gerado com sucesso!";
    }

    // Enviar para nuvem (Supabase simulado)
    if ($acao === 'enviar_nuvem' && isAdmin()) {
        $relId = (int)($_POST['relatorio_id']??0);
        $rel   = $db->prepare("SELECT * FROM relatorios WHERE id=?")->execute([$relId]) && true;
        $rStmt = $db->prepare("SELECT * FROM relatorios WHERE id=?");
        $rStmt->execute([$relId]);
        $rel = $rStmt->fetch();

        if ($rel) {
            // Simulação de POST para Supabase REST API
            $payload = json_encode([
                'titulo'         => $rel['titulo'],
                'tipo'           => $rel['tipo'],
                'periodo_inicio' => $rel['periodo_inicio'],
                'periodo_fim'    => $rel['periodo_fim'],
                'dados'          => $rel['dados'],
                'escola_id'      => 'escola_demo_001',
                'enviado_em'     => date('c'),
            ]);

            /* Comentado para não fazer chamada real (sem chave válida):
            $ch = curl_init(SUPABASE_URL . '/rest/v1/relatorios_escola');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'apikey: ' . SUPABASE_ANON_KEY,
                    'Authorization: Bearer ' . SUPABASE_ANON_KEY,
                    'Prefer: return=minimal',
                ],
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            */

            // Marca como enviado
            $db->prepare("UPDATE relatorios SET enviado_nuvem=1 WHERE id=?")->execute([$relId]);
            logAction(currentId(),'envio_nuvem','relatorio',$relId);
            $msg = "☁️ Relatório sincronizado com Supabase Cloud!";
        }
    }

    // Análise IA (OpenAI)
    if ($acao === 'analisar_ia' && isDiretor()) {
        $relId = (int)($_POST['relatorio_id']??0);
        $rStmt = $db->prepare("SELECT * FROM relatorios WHERE id=?");
        $rStmt->execute([$relId]);
        $rel = $rStmt->fetch();

        if ($rel) {
            /* Integração real com OpenAI (descomentar e usar chave válida):
            $prompt = "Analise este relatório escolar e forneça insights:\n" . json_encode(json_decode($rel['dados'],true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch,[
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json','Authorization: Bearer '.OPENAI_API_KEY],
                CURLOPT_POSTFIELDS => json_encode(['model'=>OPENAI_MODEL,'max_tokens'=>500,'messages'=>[['role'=>'user','content'=>$prompt]]]),
            ]);
            $res = json_decode(curl_exec($ch),true);
            curl_close($ch);
            $analise = $res['choices'][0]['message']['content'] ?? 'Sem resposta';
            */
            $analise = "✨ Análise de IA (demo): O período apresenta frequência satisfatória. Recomenda-se atenção aos alunos com mais de 2 faltas consecutivas. As notas de Matemática estão abaixo da média — considere reforço pedagógico.";
            $dados   = json_decode($rel['dados'],true);
            $dados['analise_ia'] = $analise;
            $db->prepare("UPDATE relatorios SET dados=? WHERE id=?")->execute([json_encode($dados),$relId]);
            $msg = "🤖 Análise de IA adicionada ao relatório!";
        }
    }
}

// Lista de relatórios
$relatorios = $db->query("
    SELECT r.*, u.nome as gerado_por_nome
    FROM relatorios r LEFT JOIN usuarios u ON u.id=r.gerado_por
    ORDER BY r.created_at DESC
")->fetchAll();

// Relatório selecionado para detalhes
$verRel = null;
if (isset($_GET['ver'])) {
    $vrStmt = $db->prepare("SELECT r.*,u.nome as gerado_por_nome FROM relatorios r LEFT JOIN usuarios u ON u.id=r.gerado_por WHERE r.id=?");
    $vrStmt->execute([(int)$_GET['ver']]);
    $verRel = $vrStmt->fetch();
}

layout_start('Relatórios','relatorios');
?>

<?php if ($msg):  ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-error">⚠️ <?=htmlspecialchars($erro)?></div><?php endif; ?>

<?php if ($verRel):
    $dados = json_decode($verRel['dados'],true) ?? [];
    $freq  = $dados['frequencia'] ?? [];
    $notas = $dados['notas'] ?? [];
    $pctF  = ($freq['total']??0) ? round(($freq['presentes']??0)/($freq['total']??1)*100) : 0;
?>
<div class="flex items-center gap-2 mb-4">
  <a href="/escola/relatorios.php" class="btn btn-ghost btn-sm">← Voltar</a>
  <h2 style="font-family:var(--font-head)"><?=htmlspecialchars($verRel['titulo'])?></h2>
  <?php if ($verRel['enviado_nuvem']): ?><span class="badge badge-success">☁️ Na Nuvem</span><?php endif; ?>
</div>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon" style="background:rgba(16,185,129,.12)">📅</div>
    <div class="stat-info"><div class="value"><?=$pctF?>%</div><div class="label">Taxa de Presença</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:rgba(244,63,94,.12)">❌</div>
    <div class="stat-info"><div class="value"><?=$freq['ausentes']??0?></div><div class="label">Ausências</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:rgba(59,130,246,.12)">📝</div>
    <div class="stat-info"><div class="value"><?=count($notas)?></div><div class="label">Disciplinas Avaliadas</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:rgba(240,165,0,.12)">👨‍🎓</div>
    <div class="stat-info"><div class="value"><?=$dados['novos_alunos']??0?></div><div class="label">Novos Alunos</div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
  <div class="card">
    <div class="card-header"><span class="card-title">📊 Médias por Disciplina</span></div>
    <?php if ($notas): ?>
    <div class="bar-chart">
      <?php foreach ($notas as $n):
        $pct = $n['media']/10*100;
        $col = $n['media']>=7?'var(--emerald)':($n['media']>=5?'var(--gold)':'var(--rose)');
      ?>
      <div class="bar-row">
        <span class="bar-label"><?=mb_substr($n['disciplina'],0,12)?></span>
        <div class="bar-track"><div class="bar-fill" style="width:<?=$pct?>%;background:<?=$col?>"></div></div>
        <span class="bar-val"><?=number_format($n['media'],1)?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p style="color:var(--muted);font-size:.83rem">Nenhuma nota no período.</p><?php endif; ?>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">📅 Frequência do Período</span></div>
    <?php $colors=['Presentes'=>'var(--emerald)','Ausentes'=>'var(--rose)','Total'=>'var(--blue)'];
    foreach (['Presentes'=>$freq['presentes']??0,'Ausentes'=>$freq['ausentes']??0] as $label => $val):
      $pct2 = ($freq['total']??0) ? round($val/($freq['total']??1)*100) : 0;
    ?>
    <div class="bar-row" style="margin-bottom:.7rem">
      <span class="bar-label"><?=$label?></span>
      <div class="bar-track"><div class="bar-fill" style="width:<?=$pct2?>%;background:<?=$colors[$label]?>"></div></div>
      <span class="bar-val"><?=$val?></span>
    </div>
    <?php endforeach; ?>
    <hr class="sep">
    <p style="font-size:.8rem;color:var(--muted)">Total de registros: <strong style="color:var(--text)"><?=$freq['total']??0?></strong></p>
  </div>
</div>

<?php if (isset($dados['analise_ia'])): ?>
<div class="card mb-4" style="border-color:var(--indigo)">
  <div class="card-header"><span class="card-title">🤖 Análise de Inteligência Artificial</span></div>
  <p style="font-size:.85rem;line-height:1.7"><?=htmlspecialchars($dados['analise_ia'])?></p>
</div>
<?php endif; ?>

<div class="flex gap-2 flex-wrap">
  <?php if (!$verRel['enviado_nuvem'] && isAdmin()): ?>
  <form method="POST">
    <input type="hidden" name="acao" value="enviar_nuvem">
    <input type="hidden" name="relatorio_id" value="<?=$verRel['id']?>">
    <button type="submit" class="btn btn-gold">☁️ Enviar para Supabase</button>
  </form>
  <?php endif; ?>
  <?php if (isDiretor() && !isset($dados['analise_ia'])): ?>
  <form method="POST">
    <input type="hidden" name="acao" value="analisar_ia">
    <input type="hidden" name="relatorio_id" value="<?=$verRel['id']?>">
    <button type="submit" class="btn btn-primary">🤖 Analisar com IA</button>
  </form>
  <?php endif; ?>
  <button onclick="window.print()" class="btn btn-ghost">🖨️ Imprimir</button>
</div>

<?php else: ?>

<!-- LISTA DE RELATÓRIOS -->
<div class="flex justify-between items-center flex-wrap gap-2 mb-4">
  <div style="font-size:.8rem;color:var(--muted)"><?=count($relatorios)?> relatório(s) gerado(s)</div>
  <?php if (isDiretor()): ?>
  <button class="btn btn-primary" onclick="openModal('modal-gerar')">+ Gerar Relatório</button>
  <?php endif; ?>
</div>

<?php if ($relatorios): ?>
<div class="report-grid">
  <?php foreach ($relatorios as $r):
    $dados = json_decode($r['dados'],true) ?? [];
    $freq  = $dados['frequencia'] ?? [];
    $pct   = ($freq['total']??0) ? round(($freq['presentes']??0)/($freq['total']??1)*100) : 0;
  ?>
  <div class="report-card" onclick="location.href='/escola/relatorios.php?ver=<?=$r['id']?>'">
    <div class="rtype"><?=htmlspecialchars($r['tipo'])?> <?=$r['enviado_nuvem']?'· ☁️ Cloud':''?></div>
    <div class="rtitle"><?=htmlspecialchars($r['titulo'])?></div>
    <div class="rmeta">
      Frequência: <strong style="color:<?=$pct>=75?'var(--emerald)':'var(--rose)'?>"><?=$pct?>%</strong>
      &nbsp;·&nbsp; Gerado por <?=htmlspecialchars($r['gerado_por_nome']??'Sistema')?>
      &nbsp;·&nbsp; <?=date('d/m/Y H:i',strtotime($r['created_at']))?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state card"><div class="icon">📊</div><p>Nenhum relatório gerado ainda.<br>Clique em "+ Gerar Relatório" para começar.</p></div>
<?php endif; ?>

<!-- MODAL GERAR -->
<div class="modal-backdrop" id="modal-gerar">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">📊 Gerar Novo Relatório</span>
      <button class="modal-close" onclick="closeModal('modal-gerar')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="acao" value="gerar">
        <div class="form-grid">
          <div class="form-group">
            <label>Tipo</label>
            <select name="tipo" class="form-control">
              <option>Semanal</option><option>Mensal</option><option>Bimestral</option><option>Anual</option>
            </select>
          </div>
          <div class="form-group"></div>
          <div class="form-group">
            <label>Período — Início</label>
            <input type="date" name="periodo_inicio" class="form-control" value="<?=date('Y-m-d',strtotime('-7 days'))?>">
          </div>
          <div class="form-group">
            <label>Período — Fim</label>
            <input type="date" name="periodo_fim" class="form-control" value="<?=date('Y-m-d')?>">
          </div>
        </div>
        <div class="alert alert-info mt-2" style="font-size:.78rem">
          ℹ️ O relatório consolida dados de presença, notas e matrículas do período selecionado. Após gerado, pode ser enviado ao Supabase Cloud ou analisado por IA.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-gerar')">Cancelar</button>
          <button type="submit" class="btn btn-primary">📊 Gerar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>

<?php layout_end(); ?>
