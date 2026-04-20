# 🎓 EduGestor v3 — Gestão Escolar Full-Stack

[![React](https://img.shields.io/badge/React-18-61dafb)](https://react.dev) [![PHP](https://img.shields.io/badge/PHP-8.x-777bb4)](https://php.net) [![TypeScript](https://img.shields.io/badge/TypeScript-5.x-3178c6)](https://typescriptlang.org) [![Tailwind](https://img.shields.io/badge/Tailwind-4.0-38b2ac)](https://tailwindcss.com) [![License](https://img.shields.io/badge/License-MIT-yellow)](LICENSE)

Plataforma full-stack para gestão escolar integrada com Supabase, SendGrid e OpenAI.

## ✨ Funcionalidades

- 👥 **CRUD de Alunos** — Cadastro, edição, exclusão com filtros
- 📝 **Lançamento de Notas** — Interface em tempo real, histórico de mudanças
- 📍 **Chamada Digital** — Controle de frequência, justificativas
- 📊 **Dashboard** — KPIs em tempo real, gráficos interativos
- 📈 **Relatórios** — Geração em PDF/CSV, envio automático por email
- 🔐 **Autenticação JWT** — RBAC (admin, professor, aluno)
- ☁️ **Sync Cloud** — Supabase para backup, SendGrid para notificações

## 📸 Interface

| | |
|---|---|
| ![Login](./escola/screenshots/login.png)| ![Dashboard](./escola/screenshots/dashboard.png) |
| ![Notas](./escola/screenshots/notas.png) | ![Presença](./escola/screenshots/presenca.png) |
| ![Professores](./escola/screenshots/professores.png) | ![Relatórios](./escola/screenshots/relatorios.png) |
| ![Alunos](./escola/screenshots/alunos.png)| ![Usuários](./escola/screenshots/usuarios.png) |

## 🛠️ Tech Stack

**Frontend:** React 18 + TypeScript + Tailwind CSS + Vite  
**Backend:** PHP 8.x + SQLite + PDO (prepared statements)  
**Cloud:** Supabase (database), SendGrid (email), OpenAI (insights)

## 🚀 Quickstart

### Backend
```bash
cp .env.example .env
nano .env  # SUPABASE_URL, SENDGRID_API_KEY, JWT_SECRET
php -S localhost:8000 -t public/
```

### Frontend
```bash
cd frontend
npm install
npm run dev  # http://localhost:5173
```

### Login
```
Email: admin
Senha: admin123
```

## 📁 Estrutura

```
├── api/               # Backend PHP
├── frontend/          # React + TypeScript
├── database/          # Schema SQLite
├── screenshots/       # 8 screenshots
└── .env.example       # Variáveis
```

## 🔐 Segurança

✅ JWT com expiração | ✅ RBAC (roles) | ✅ SQL Injection prevention  
✅ XSS protection | ✅ CORS restritivo | ✅ Rate limiting | ✅ HTTPS produção

## 👤 Autor

**Roberto Átila Almeida Azevedo**

- 🔗 GitHub: [@robertoatila](https://github.com/robertoatila)
- 🔗 LinkedIn: [Roberto Átila](https://www.linkedin.com/in/roberto-átila-almeida-azevedo-0a64412b4/)
- 📧 Email: roberto.atila10@gmail.com

---

⭐ Se foi útil, dê uma estrela!