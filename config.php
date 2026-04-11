<?php
// ============================================================
//  EduGestor — Configurações Gerais
// ============================================================

define('APP_NAME',    'EduGestor');
define('APP_VERSION', '2.1.0');
define('DB_PATH',     __DIR__ . '/database/escola.db');

// ── Hierarquia de cargos ─────────────────────────────────────
// 1 = Admin  |  2 = Diretor  |  3 = Secretaria  |  4 = Professor
define('ROLE_ADMIN',      1);
define('ROLE_DIRETOR',    2);
define('ROLE_SECRETARIA', 3);
define('ROLE_PROFESSOR',  4);

$ROLE_NAMES = [
    ROLE_ADMIN      => 'Administrador',
    ROLE_DIRETOR    => 'Diretor',
    ROLE_SECRETARIA => 'Secretaria',
    ROLE_PROFESSOR  => 'Professor',
];

$ROLE_COLORS = [
    ROLE_ADMIN      => '#f59e0b',
    ROLE_DIRETOR    => '#6366f1',
    ROLE_SECRETARIA => '#10b981',
    ROLE_PROFESSOR  => '#3b82f6',
];

// ── Credenciais padrão (altere após instalação!) ─────────────
$DEFAULT_USERS = [
    ['login'=>'admin',      'senha'=>'admin123',    'nome'=>'Administrador', 'cargo'=>ROLE_ADMIN],
    ['login'=>'diretor',    'senha'=>'diretor123',  'nome'=>'Diretor Geral', 'cargo'=>ROLE_DIRETOR],
    ['login'=>'secretaria', 'senha'=>'sec123',      'nome'=>'Secretaria',    'cargo'=>ROLE_SECRETARIA],
    ['login'=>'professor',  'senha'=>'prof123',     'nome'=>'Prof. Demo',    'cargo'=>ROLE_PROFESSOR],
];

// ── Integração com API / Nuvem ───────────────────────────────
// Supabase (relatórios e backup em nuvem)
define('SUPABASE_URL',     'https://xyzcompany.supabase.co');
define('SUPABASE_ANON_KEY','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.DEMO_TOKEN');

// OpenAI — análise inteligente de relatórios
define('OPENAI_API_KEY',  'sk-proj-DEMO_KEY_REPLACE_ME');
define('OPENAI_MODEL',    'gpt-4o-mini');

// Webhook para notificações (ex: Discord / Slack)
define('WEBHOOK_URL',     'https://hooks.example.com/services/DEMO');

// SendGrid — envio de relatórios por e-mail
define('SENDGRID_API_KEY','SG.DEMO_KEY_REPLACE_ME');
define('EMAIL_FROM',      'noreply@edugestor.com.br');

// ── Sessão ───────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600); // 1 hora

session_start();
