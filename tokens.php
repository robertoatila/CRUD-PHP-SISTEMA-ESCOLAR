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
        $nome    = trim($_POST['nome']??'');
        $servico = trim($_POST['servico']??'');
        $token   = trim($_POST['token']??'');
        $desc    = trim($_POST['descricao']??'');
        if (!$nome || !$servico || !$token) { $erro = 'Nome, serviço e token são obrigatórios.'; }
        else {
            $db->prepare("INSERT INTO tokens_api (nome,servico,token,descricao,created_by) VALUES (?,?,?,?,?)")
               ->execute([$nome,$servico,$token,$desc,currentId()]);
            logAction(currentId(),'criar','token',(int)$db->lastInsertId());
            $msg = "✅ Token <strong>$nome</strong> adicionado.";
        }
    }

    if ($acao === 'toggle') {
        $id   = (int)($_POST['id']??0);
        $ativ = (int)($_POST['ativo']??0);
        $db->prepare("UPDATE tokens_api SET ativo=? WHERE id=?")->execute([$ativ ? 0 : 1, $id]);
        $msg = "✅ Status do token atualizado.";
    }

    if ($acao === 'deletar') {
        $id = (int)($_POST['id']??0);
        $db->prepare("DELETE FROM tokens_api WHERE id=?")->execute([$id]);
        logAction(currentId(),'deletar','token',$id);
        $msg = "🗑️ Token removido.";
    }

    // Testar token (simulação)
    if ($acao === 'testar') {
        $id    = (int)($_POST['id']??0);
        $tStmt = $db->prepare("SELECT * FROM tokens_api WHERE id=?");
        $tStmt->execute([$id]);
        $tk = $tStmt->fetch();
        if ($tk) {
            // Simulação — em produção faria curl para a API
            $db->prepare("UPDATE tokens_api SET ultimo_uso=datetime('now') WHERE id=?")->execute([$id]);
            $msg = "✅ Conexão com <strong>{$tk['servico']}</strong> testada com sucesso! (simulado)";
        }
    }
}

$tokens = $db->query("SELECT t.*, u.nome as criado_por_nome FROM tokens_api t LEFT JOIN usuarios u ON u.id=t.created_by ORDER BY t.created_at DESC")->fetchAll();

$SERVICOS = ['Supabase','OpenAI','SendGrid','Firebase','AWS S3','Google Cloud','Slack Webhook','Discord Webhook','Custom REST'];

layout_start('Tokens de API','tokens');
?>

<?php if ($msg):  ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-error">⚠️ <?=htmlspecialchars($erro)?></div><?php endif; ?>

<div class="alert alert-info mb-4">
  🔑 <strong>Gerenciamento de Tokens de API</strong> — Esta área é restrita ao Administrador.
  Os tokens são usados para integração com serviços de nuvem como Supabase (backup), OpenAI (análise IA),
  SendGrid (e-mail) e webhooks de notificação.
</div>

<div class="flex justify-between items-center mb-4">
  <div style="font-size:.8rem;color:var(--muted)"><?=count($tokens)?> token(s) configurado(s)</div>
  <button class="btn btn-gold" onclick="openModal('modal-token')">+ Adicionar Token</button>
</div>

<!-- Cards de integrações disponíveis -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.8rem;margin-bottom:1.5rem">
  <?php
  $integracoes = [
    ['☁️','Supabase','Banco de dados e armazenamento em nuvem. Usado para backup de relatórios.','var(--emerald)'],
    ['🤖','OpenAI','Análise inteligente de relatórios e insights pedagógicos com GPT.','var(--indigo2)'],
    ['📧','SendGrid','Envio automático de relatórios por e-mail para responsáveis.','var(--blue)'],
    ['🔔','Webhooks','Notificações em tempo real para Slack, Discord ou sistemas próprios.','var(--gold)'],
  ];
  foreach ($integracoes as [$ic,$nm,$desc,$color]):
  ?>
  <div style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:1rem;border-top:3px solid <?=$color?>">
    <div style="font-size:1.4rem;margin-bottom:.3rem"><?=$ic?></div>
    <div style="font-weight:700;font-size:.88rem"><?=$nm?></div>
    <div style="font-size:.75rem;color:var(--muted);margin-top:.2rem"><?=$desc?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Lista de tokens -->
<div class="card">
  <div class="card-header"><span class="card-title">🔑 Tokens Configurados</span></div>
  <?php if ($tokens): ?>
  <div style="display:flex;flex-direction:column;gap:.7rem">
    <?php foreach ($tokens as $tk):
      $ic = match(true){
        str_contains($tk['servico'],'Supabase') => '☁️',
        str_contains($tk['servico'],'OpenAI')   => '🤖',
        str_contains($tk['servico'],'SendGrid')  => '📧',
        str_contains($tk['servico'],'Webhook')   => '🔔',
        str_contains($tk['servico'],'Firebase')  => '🔥',
        str_contains($tk['servico'],'AWS')        => '🟠',
        str_contains($tk['servico'],'Google')    => '🟡',
        default => '🔑',
      };
      $maskedToken = substr($tk['token'],0,8) . '••••••••••••' . substr($tk['token'],-4);
    ?>
    <div class="token-card">
      <div style="font-size:1.6rem"><?=$ic?></div>
      <div class="token-info" style="flex:1">
        <div class="svc"><?=htmlspecialchars($tk['servico'])?></div>
        <div class="name"><?=htmlspecialchars($tk['nome'])?></div>
        <div class="token-value" id="tv-<?=$tk['id']?>"><?=htmlspecialchars($maskedToken)?></div>
        <?php if ($tk['descricao']): ?><div style="font-size:.72rem;color:var(--muted);margin-top:.2rem"><?=htmlspecialchars($tk['descricao'])?></div><?php endif; ?>
        <div style="font-size:.7rem;color:var(--muted);margin-top:.3rem">
          Criado por <?=htmlspecialchars($tk['criado_por_nome']??'Sistema')?> · 
          <?=date('d/m/Y',strtotime($tk['created_at']))?>
          <?=$tk['ultimo_uso']?' · Último uso: '.date('d/m/Y H:i',strtotime($tk['ultimo_uso'])):''?>
        </div>
      </div>
      <div class="flex items-center gap-2" style="flex-shrink:0">
        <span class="badge <?=$tk['ativo']?'badge-success':'badge-danger'?>"><?=$tk['ativo']?'Ativo':'Inativo'?></span>
        <button class="btn btn-ghost btn-xs" onclick="toggleToken('tv-<?=$tk['id']?>','<?=htmlspecialchars($tk['token'],ENT_QUOTES)?>')">👁</button>
        <form method="POST" style="display:inline">
          <input type="hidden" name="acao" value="testar">
          <input type="hidden" name="id" value="<?=$tk['id']?>">
          <button type="submit" class="btn btn-ghost btn-xs">🔌 Testar</button>
        </form>
        <form method="POST" style="display:inline">
          <input type="hidden" name="acao" value="toggle">
          <input type="hidden" name="id" value="<?=$tk['id']?>">
          <input type="hidden" name="ativo" value="<?=$tk['ativo']?>">
          <button type="submit" class="btn btn-ghost btn-xs"><?=$tk['ativo']?'⏸':'▶'?></button>
        </form>
        <form method="POST" onsubmit="return confirm('Remover token?')" style="display:inline">
          <input type="hidden" name="acao" value="deletar">
          <input type="hidden" name="id" value="<?=$tk['id']?>">
          <button type="submit" class="btn btn-danger btn-xs">🗑️</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state"><div class="icon">🔑</div><p>Nenhum token configurado.</p></div>
  <?php endif; ?>
</div>

<!-- MODAL ADICIONAR TOKEN -->
<div class="modal-backdrop" id="modal-token">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">🔑 Adicionar Token de API</span>
      <button class="modal-close" onclick="closeModal('modal-token')">✕</button>
    </div>
    <div class="modal-body">
      <div class="alert alert-warn mb-3">⚠️ Mantenha tokens de API em segurança. Nunca compartilhe com usuários sem permissão.</div>
      <form method="POST">
        <input type="hidden" name="acao" value="criar">
        <div class="form-grid">
          <div class="form-group"><label>Nome do Token *</label><input type="text" name="nome" class="form-control" placeholder="Ex: Supabase Produção" required></div>
          <div class="form-group"><label>Serviço *</label>
            <select name="servico" class="form-control">
              <?php foreach ($SERVICOS as $s): ?><option><?=$s?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Token / Chave de API *</label>
            <div class="input-wrap">
              <input type="password" name="token" id="token-input" class="form-control" placeholder="sk-... ou eyJ..." required>
              <button type="button" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer" onclick="t=document.getElementById('token-input');t.type=t.type==='password'?'text':'password'">👁</button>
            </div>
          </div>
          <div class="form-group" style="grid-column:1/-1"><label>Descrição</label><textarea name="descricao" class="form-control" placeholder="Para que este token é usado?"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-token')">Cancelar</button>
          <button type="submit" class="btn btn-gold">🔑 Salvar Token</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleToken(id, realToken){
  const el = document.getElementById(id);
  const masked = realToken.substring(0,8) + '••••••••••••' + realToken.slice(-4);
  el.textContent = el.textContent.includes('•') ? realToken : masked;
}
</script>

<?php layout_end(); ?>
