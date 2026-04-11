<?php
require_once __DIR__ . '/../config.php';

if (!is_dir(dirname(DB_PATH))) {
    mkdir(dirname(DB_PATH), 0755, true);
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    initDB($pdo);
    return $pdo;
}

function initDB(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS usuarios (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        login       TEXT UNIQUE NOT NULL,
        senha_hash  TEXT NOT NULL,
        nome        TEXT NOT NULL,
        email       TEXT,
        cargo       INTEGER NOT NULL,
        ativo       INTEGER DEFAULT 1,
        foto        TEXT,
        api_token   TEXT,
        created_at  TEXT DEFAULT (datetime('now')),
        updated_at  TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS alunos (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        matricula     TEXT UNIQUE NOT NULL,
        nome          TEXT NOT NULL,
        email         TEXT,
        telefone      TEXT,
        data_nascimento TEXT,
        turma         TEXT,
        serie         TEXT,
        turno         TEXT DEFAULT 'Matutino',
        responsavel   TEXT,
        tel_responsavel TEXT,
        endereco      TEXT,
        ativo         INTEGER DEFAULT 1,
        foto          TEXT,
        observacoes   TEXT,
        created_at    TEXT DEFAULT (datetime('now')),
        updated_at    TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS professores (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        registro      TEXT UNIQUE NOT NULL,
        usuario_id    INTEGER REFERENCES usuarios(id),
        nome          TEXT NOT NULL,
        email         TEXT,
        telefone      TEXT,
        disciplina    TEXT,
        formacao      TEXT,
        turmas        TEXT,
        ativo         INTEGER DEFAULT 1,
        foto          TEXT,
        created_at    TEXT DEFAULT (datetime('now')),
        updated_at    TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS notas (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        aluno_id      INTEGER NOT NULL REFERENCES alunos(id) ON DELETE CASCADE,
        professor_id  INTEGER REFERENCES professores(id),
        disciplina    TEXT NOT NULL,
        bimestre      INTEGER NOT NULL,
        nota          REAL NOT NULL CHECK(nota >= 0 AND nota <= 10),
        tipo          TEXT DEFAULT 'Prova',
        observacao    TEXT,
        data_lancamento TEXT DEFAULT (date('now')),
        created_at    TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS presenca (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        aluno_id      INTEGER NOT NULL REFERENCES alunos(id) ON DELETE CASCADE,
        professor_id  INTEGER REFERENCES professores(id),
        disciplina    TEXT NOT NULL,
        data          TEXT NOT NULL,
        status        TEXT DEFAULT 'Presente' CHECK(status IN ('Presente','Ausente','Justificado')),
        observacao    TEXT,
        created_at    TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS tokens_api (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        nome          TEXT NOT NULL,
        servico       TEXT NOT NULL,
        token         TEXT NOT NULL,
        descricao     TEXT,
        ativo         INTEGER DEFAULT 1,
        ultimo_uso    TEXT,
        created_by    INTEGER REFERENCES usuarios(id),
        created_at    TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS relatorios (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        titulo        TEXT NOT NULL,
        tipo          TEXT DEFAULT 'Semanal',
        periodo_inicio TEXT,
        periodo_fim   TEXT,
        dados         TEXT,
        gerado_por    INTEGER REFERENCES usuarios(id),
        enviado_nuvem INTEGER DEFAULT 0,
        created_at    TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS logs (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER,
        acao       TEXT,
        entidade   TEXT,
        entidade_id INTEGER,
        ip         TEXT,
        created_at TEXT DEFAULT (datetime('now'))
    );
    ");

    seedDefaults($pdo);
}

function seedDefaults(PDO $pdo): void {
    global $DEFAULT_USERS;
    $count = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ($count > 0) return;

    $stmt = $pdo->prepare("INSERT INTO usuarios (login,senha_hash,nome,cargo) VALUES (?,?,?,?)");
    foreach ($DEFAULT_USERS as $u) {
        $hash = password_hash($u['senha'], PASSWORD_DEFAULT);
            $stmt->execute([$u['login'], $hash, $u['nome'], $u['cargo']]);
    }

    // Alunos demo
    $alunos = [
        ['2024001','Ana Clara Silva','ana@escola.com','(11)9999-1111','2010-03-15','A','7º Ano','Matutino','Maria Silva','(11)8888-0001'],
        ['2024002','Bruno Santos',  'bruno@escola.com','(11)9999-2222','2009-07-22','A','8º Ano','Matutino','Carlos Santos','(11)8888-0002'],
        ['2024003','Carla Mendes',  'carla@escola.com','(11)9999-3333','2011-01-05','B','6º Ano','Vespertino','Paula Mendes','(11)8888-0003'],
        ['2024004','Diego Rocha',   'diego@escola.com','(11)9999-4444','2008-12-30','B','9º Ano','Matutino','Roberto Rocha','(11)8888-0004'],
        ['2024005','Eduarda Lima',  'edu@escola.com', '(11)9999-5555','2010-08-18','C','7º Ano','Vespertino','Lúcia Lima','(11)8888-0005'],
    ];
    $sa = $pdo->prepare("INSERT INTO alunos (matricula,nome,email,telefone,data_nascimento,turma,serie,turno,responsavel,tel_responsavel) VALUES (?,?,?,?,?,?,?,?,?,?)");
    foreach ($alunos as $a) $sa->execute($a);

    // Professor demo
    $pdo->exec("INSERT INTO professores (registro,nome,email,disciplina,formacao,turmas) VALUES ('P001','Prof. João Oliveira','joao@escola.com','Matemática','Licenciatura em Matemática - USP','7A,8A,9B')");

    // Notas demo
    $notas = [[1,1,'Matemática',1,8.5,'Prova'],[1,1,'Matemática',2,7.0,'Prova'],[2,1,'Matemática',1,9.0,'Prova'],[3,1,'Matemática',1,6.5,'Trabalho']];
    $sn = $pdo->prepare("INSERT INTO notas (aluno_id,professor_id,disciplina,bimestre,nota,tipo) VALUES (?,?,?,?,?,?)");
    foreach ($notas as $n) $sn->execute($n);

    // Presenças demo
    $datas = ['2025-01-20','2025-01-21','2025-01-22','2025-01-23','2025-01-24'];
    $sp = $pdo->prepare("INSERT INTO presenca (aluno_id,professor_id,disciplina,data,status) VALUES (?,?,?,?,?)");
    foreach ($datas as $d) {
        $sp->execute([1,1,'Matemática',$d,'Presente']);
        $sp->execute([2,1,'Matemática',$d, $d=='2025-01-22'?'Ausente':'Presente']);
        $sp->execute([3,1,'Matemática',$d,'Presente']);
    }

    // Token demo
    $pdo->exec("INSERT INTO tokens_api (nome,servico,token,descricao,created_by) VALUES ('Supabase Prod','Supabase','".SUPABASE_ANON_KEY."','Backup e sincronização em nuvem',1)");
}
