// ── EduGestor · app.js ───────────────────────────────────────

// ── Modais ───────────────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.classList.remove('open');
    document.body.style.overflow = '';
  }
}

// Fechar modal clicando no backdrop
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-backdrop')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

// Fechar modal com ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop.open').forEach(m => {
      m.classList.remove('open');
      document.body.style.overflow = '';
    });
  }
});

// ── Tabs ─────────────────────────────────────────────────────
function switchTab(btn, tabId) {
  // Desativa todos os tabs e painéis do contêiner
  const container = btn.closest('.tabs');
  if (!container) return;

  container.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');

  // Esconde todos os painéis irmãos
  const panels = document.querySelectorAll('[id^="tab-"]');
  panels.forEach(p => p.style.display = 'none');

  const target = document.getElementById(tabId);
  if (target) target.style.display = '';
}

// ── Bar chart animation ───────────────────────────────────────
function animateBars() {
  document.querySelectorAll('.bar-fill').forEach(bar => {
    const w = bar.style.width;
    bar.style.width = '0';
    requestAnimationFrame(() => {
      setTimeout(() => { bar.style.width = w; }, 100);
    });
  });
}

// ── Alerts auto-hide ──────────────────────────────────────────
document.querySelectorAll('.alert').forEach(alert => {
  setTimeout(() => {
    alert.style.transition = 'opacity .5s';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 500);
  }, 5000);
});

// ── Confirm danger actions ────────────────────────────────────
document.querySelectorAll('.btn-danger[type="submit"]').forEach(btn => {
  btn.addEventListener('click', e => {
    if (!confirm('Tem certeza? Esta ação não pode ser desfeita.')) e.preventDefault();
  });
});

// ── Sidebar toggle (mobile) ───────────────────────────────────
document.addEventListener('click', e => {
  const sidebar = document.getElementById('sidebar');
  if (!sidebar) return;
  if (e.target.classList.contains('hamburger')) return;
  if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(e.target)) {
    sidebar.classList.remove('open');
  }
});

// ── Table row click to select (highlight) ─────────────────────
document.querySelectorAll('tbody tr').forEach(row => {
  row.addEventListener('click', e => {
    if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A' || e.target.closest('form')) return;
    document.querySelectorAll('tbody tr').forEach(r => r.style.background = '');
    row.style.background = 'rgba(99,102,241,.06)';
  });
});

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  animateBars();

  // Stat cards appear animation
  document.querySelectorAll('.stat-card').forEach((card, i) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(10px)';
    card.style.transition = `opacity .3s ${i * 0.06}s, transform .3s ${i * 0.06}s`;
    requestAnimationFrame(() => {
      card.style.opacity = '1';
      card.style.transform = 'translateY(0)';
    });
  });
});
