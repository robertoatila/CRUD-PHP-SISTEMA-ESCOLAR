<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

function login(string $login, string $senha): bool {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE login = ? AND ativo = 1");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    $valido = password_verify($senha, $user['senha_hash']);
        if ($user && $valido) {
        $_SESSION['user']       = $user;
        $_SESSION['logged_at']  = time();
        logAction($user['id'], 'login', 'session', 0);
        return true;
    }
    return false;
}

function logout(): void {
    if (isset($_SESSION['user'])) {
        logAction($_SESSION['user']['id'], 'logout', 'session', 0);
    }
    session_destroy();
    header('Location: /index.php');
    exit;
}

function requireLogin(): void {
    if (!isset($_SESSION['user'])) {
        header('Location: /index.php?msg=session_expired');
        exit;
    }
    if ((time() - ($_SESSION['logged_at'] ?? 0)) > SESSION_LIFETIME) {
        logout();
    }
}

function requireRole(int ...$roles): void {
    requireLogin();
    if (!in_array(currentCargo(), $roles, true)) {
        http_response_code(403);
        die(renderError('Acesso negado. Você não tem permissão para esta área.'));
    }
}

// ── Helpers de sessão ────────────────────────────────────────

function currentUser(): array { return $_SESSION['user'] ?? []; }
function currentCargo(): int  { return (int)($_SESSION['user']['cargo'] ?? 99); }
function currentNome(): string { return $_SESSION['user']['nome'] ?? 'Desconhecido'; }
function currentId(): int     { return (int)($_SESSION['user']['id'] ?? 0); }

function isAdmin():      bool { return currentCargo() === ROLE_ADMIN; }
function isDiretor():    bool { return currentCargo() <= ROLE_DIRETOR; }
function isSecretaria(): bool { return currentCargo() <= ROLE_SECRETARIA; }
function isProfessor():  bool { return currentCargo() <= ROLE_PROFESSOR; }

function canEdit(): bool    { return currentCargo() <= ROLE_SECRETARIA; }
function canDelete(): bool  { return currentCargo() <= ROLE_DIRETOR; }
function canManageUsers(): bool { return currentCargo() === ROLE_ADMIN; }

// ── Log de ações ─────────────────────────────────────────────

function logAction(int $userId, string $acao, string $entidade, int $id): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $db->prepare("INSERT INTO logs (usuario_id,acao,entidade,entidade_id,ip) VALUES (?,?,?,?,?)")
           ->execute([$userId, $acao, $entidade, $id, $ip]);
    } catch (\Throwable $e) {}
}

// ── Badge de cargo ───────────────────────────────────────────

function badgeCargo(int $cargo): string {
    global $ROLE_NAMES, $ROLE_COLORS;
    $nome  = $ROLE_NAMES[$cargo]  ?? 'Desconhecido';
    $color = $ROLE_COLORS[$cargo] ?? '#888';
    return "<span class=\"badge\" style=\"background:$color\">$nome</span>";
}

// ── Erro HTML ────────────────────────────────────────────────

function renderError(string $msg): string {
    return "<!DOCTYPE html><html><body style='font-family:sans-serif;text-align:center;padding:4rem'>
        <h2 style='color:#e11d48'>⛔ $msg</h2>
        <a href='javascript:history.back()' style='color:#6366f1'>← Voltar</a>
    </body></html>";
}
