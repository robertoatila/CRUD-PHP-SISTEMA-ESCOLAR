# 🎓 EduGestor — Sistema de Gerenciamento Escolar

Sistema CRUD completo para gestão escolar com 4 níveis de hierarquia, integração com APIs de nuvem e relatórios semanais.

---

## 📋 Requisitos
- PHP 8.1+ com extensão PDO e PDO_SQLite habilitadas
- Servidor Apache ou Nginx
- Permissão de escrita no diretório `database/`

---

## ⚡ Instalação

### 1. Copie os arquivos
```bash
cp -r escola/ /var/www/html/escola
chmod 755 /var/www/html/escola
mkdir -p /var/www/html/escola/database
chmod 777 /var/www/html/escola/database
```

### 2. Acesse pelo navegador
```
http://localhost/escola/
```
O banco de dados SQLite será criado automaticamente na primeira execução.

### 3. Credenciais padrão
| Cargo        | Nível | Login      | Senha      |
|-------------|-------|------------|------------|
| Administrador | 1   | admin      | admin123   |
| Diretor       | 2   | diretor    | diretor123 |
| Secretaria    | 3   | secretaria | sec123     |
| Professor     | 4   | professor  | prof123    |

> ⚠️ **Altere as senhas após a instalação!**

---

## 🗂️ Estrutura de arquivos
```
escola/
├── index.php          — Tela de login
├── dashboard.php      — Painel principal
├── alunos.php         — CRUD de alunos
├── professores.php    — CRUD de professores
├── notas.php          — Notas e boletim
├── presenca.php       — Chamada e frequência
├── relatorios.php     — Relatórios semanais + nuvem + IA
├── usuarios.php       — Gestão de usuários (Admin)
├── tokens.php         — Tokens de API (Admin)
├── logout.php         — Encerrar sessão
├── config.php         — ⚙️ Configurações gerais
├── .htaccess          — Proteção de arquivos
├── database/          — SQLite (criado automaticamente)
├── assets/
│   ├── style.css      — Estilos globais
│   └── app.js         — JavaScript
└── includes/
    ├── db.php         — Banco de dados
    ├── auth.php       — Autenticação
    └── layout.php     — Layout compartilhado
```

---

## 🔑 Hierarquia de Cargos

| Nível | Cargo       | Permissões                                      |
|-------|-------------|------------------------------------------------|
| 1     | Admin       | Tudo: usuários, tokens, relatórios, CRUD total |
| 2     | Diretor     | Alunos, professores, relatórios, análise IA    |
| 3     | Secretaria  | Cadastrar e editar alunos e professores        |
| 4     | Professor   | Lançar notas e chamadas das próprias turmas    |

---

## ☁️ Integrações de API

Configure os tokens em **Admin → Tokens de API**:

| Serviço    | Uso                              | Onde obter                        |
|-----------|----------------------------------|-----------------------------------|
| Supabase  | Backup de relatórios em nuvem    | supabase.com → Project Settings   |
| OpenAI    | Análise IA de relatórios         | platform.openai.com → API Keys    |
| SendGrid  | Envio de relatórios por e-mail   | sendgrid.com → Settings → API Keys |
| Webhooks  | Notificações (Slack/Discord)     | Nas configurações do seu workspace |

Para ativar os serviços reais, edite `config.php` com suas chaves e
descomente os blocos `curl_*` em `relatorios.php`.

---

## 🔒 Segurança

- Senhas armazenadas com `password_hash(BCRYPT)`
- Sessões com timeout de 1 hora
- Controle de acesso por cargo em cada rota
- Banco de dados bloqueado via `.htaccess`
- Log de todas as ações por usuário
- Tokens de API mascarados na interface

---

## 📊 Funcionalidades

- ✅ Login com 4 níveis de hierarquia
- ✅ CRUD completo de alunos (matrícula automática)
- ✅ CRUD completo de professores
- ✅ Lançamento de notas por bimestre e tipo
- ✅ Boletim geral com situação (Aprovado / Recuperação / Reprovado)
- ✅ Chamada digital com status (Presente / Ausente / Justificado)
- ✅ Frequência por aluno com percentual
- ✅ Relatórios semanais/mensais/bimestrais
- ✅ Envio de relatórios para Supabase Cloud
- ✅ Análise de relatórios com OpenAI GPT
- ✅ Gerenciamento de tokens de API
- ✅ Log de auditoria de todas as ações
- ✅ Interface responsiva (mobile-friendly)
- ✅ Tema dark acadêmico profissional

---

*EduGestor v2.1.0 — Desenvolvido para gestão escolar completa*
