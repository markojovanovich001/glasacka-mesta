<?php
/**
 * layout.php – Shared admin panel layout
 *
 * Usage (from root files like index.php):
 *   require_once __DIR__ . '/layout.php';
 *   layout_start('Page Title', 'nav-id');
 *   ... echo page content ...
 *   layout_end();
 *
 * Usage (from pages/detail.php):
 *   require_once dirname(__DIR__) . '/layout.php';
 *   layout_start('Page Title', 'nav-id', [['Dashboard','../index.php'],['Detail',null]]);
 *   ... echo page content ...
 *   layout_end();
 *
 * $activeNav values: 'dashboard', 'izmene', 'log'
 */

require_once __DIR__ . '/db.php';

/**
 * Detect base path ('' when called from root, '../' when called from pages/).
 */
function _layout_base(): string {
    $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
    $rootDir   = realpath(__DIR__);
    return ($scriptDir !== $rootDir) ? '../' : '';
}

function layout_start(
    string $pageTitle,
    string $activeNav   = 'dashboard',
    array  $breadcrumbs = []  // [ ['Label','url|null'], ... ]
): void {
    $db   = getDB();
    $base = _layout_base();

    $changesCount = (int)($db->query(
        "SELECT COUNT(*) c FROM rik_fajlovi WHERE has_changes=1 AND aktivan=1"
    )->fetch_assoc()['c'] ?? 0);

    $totalFajlova = (int)($db->query(
        "SELECT COUNT(*) c FROM rik_fajlovi WHERE aktivan=1"
    )->fetch_assoc()['c'] ?? 0);

    $lastCron = '';
    $lastRunFile = storagePath('logs', 'last_run.txt');
    if (is_file($lastRunFile)) {
        $lastCron = trim(file_get_contents($lastRunFile));
    }

    $navItems = [
        ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'href' => $base . 'index.php',       'badge' => $changesCount],
        ['id' => 'check',     'label' => 'Check',      'icon' => 'bi-search',       'href' => $base . 'pages/cron.php'],
        ['id' => 'export',    'label' => 'Export',     'icon' => 'bi-download',     'href' => $base . 'pages/export.php'],
    ];
    ?>
<!DOCTYPE html>
<html lang="sr-Latn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> – GM Monitor</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
/* ── Variables ───────────────────────────────────────────── */
:root {
  --sidebar-width: 240px;
  --sidebar-bg:    #0f172a;
  --sidebar-hover: #1e293b;
  --sidebar-active:#1d4ed8;
  --sidebar-text:  #94a3b8;
  --sidebar-light: #f8fafc;
  --topbar-height: 58px;
  --body-bg:       #f1f5f9;
  --card-border:   #e2e8f0;
  --accent:        #3b82f6;
}

/* ── Reset ───────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }
body {
  margin: 0;
  font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
  background: var(--body-bg);
  overflow-x: hidden;
}

/* ── Sidebar ─────────────────────────────────────────────── */
.sidebar {
  position: fixed; top: 0; left: 0;
  width: var(--sidebar-width);
  height: 100vh;
  background: var(--sidebar-bg);
  display: flex;
  flex-direction: column;
  z-index: 1040;
  transition: transform .25s ease;
  overflow-y: auto;
}
.sidebar-brand {
  display: flex; align-items: center; gap: 10px;
  padding: 18px 20px;
  color: var(--sidebar-light);
  font-weight: 700;
  font-size: .95rem;
  border-bottom: 1px solid rgba(255,255,255,.08);
  text-decoration: none;
  letter-spacing: .3px;
}
.sidebar-brand .brand-icon {
  width: 34px; height: 34px;
  background: var(--accent);
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; color: #fff; flex-shrink: 0;
}
.nav-section-label {
  font-size: .68rem;
  font-weight: 600;
  letter-spacing: .08em;
  color: #475569;
  text-transform: uppercase;
  padding: 16px 20px 6px;
}
.sidebar-nav { list-style: none; padding: 8px 0; margin: 0; flex: 1; }
.sidebar-nav li a {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 20px;
  color: var(--sidebar-text);
  text-decoration: none;
  font-size: .875rem;
  border-left: 3px solid transparent;
  transition: background .15s, color .15s, border-color .15s;
}
.sidebar-nav li a:hover {
  background: var(--sidebar-hover);
  color: var(--sidebar-light);
}
.sidebar-nav li a.active {
  background: rgba(59,130,246,.15);
  color: #93c5fd;
  border-left-color: var(--accent);
}
.sidebar-nav li a .nav-badge {
  margin-left: auto;
  background: #dc2626;
  color: #fff;
  font-size: .65rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 10px;
  line-height: 1.4;
}
.sidebar-footer {
  padding: 14px 20px;
  border-top: 1px solid rgba(255,255,255,.08);
  font-size: .75rem;
  color: #475569;
}

/* ── Main wrapper ────────────────────────────────────────── */
.main-wrapper {
  margin-left: var(--sidebar-width);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  transition: margin-left .25s ease;
}

/* ── Topbar ──────────────────────────────────────────────── */
.topbar {
  height: var(--topbar-height);
  background: #fff;
  border-bottom: 1px solid var(--card-border);
  display: flex;
  align-items: center;
  padding: 0 24px;
  position: sticky;
  top: 0;
  z-index: 1030;
  gap: 12px;
}
.topbar-hamburger {
  display: none;
  background: none; border: none; padding: 6px;
  color: #374151; font-size: 1.25rem; cursor: pointer;
  border-radius: 6px;
  line-height: 1;
}
.topbar-title {
  font-size: 1rem;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
  flex: 1;
}
.topbar-actions { display: flex; align-items: center; gap: 10px; }

/* ── Breadcrumb ──────────────────────────────────────────── */
.page-header {
  padding: 12px 24px 0;
}
.page-header .breadcrumb { font-size: .82rem; margin: 0; }
.page-header .breadcrumb-item + .breadcrumb-item::before { color: #94a3b8; }

/* ── Content ─────────────────────────────────────────────── */
.main-content { padding: 20px 24px 32px; flex: 1; }

/* ── Cards ───────────────────────────────────────────────── */
.stat-card {
  background: #fff;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  padding: 18px 20px;
  display: flex; align-items: center; gap: 14px;
  box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.stat-card .stat-icon {
  width: 44px; height: 44px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; flex-shrink: 0;
}
.stat-card .stat-value { font-size: 1.5rem; font-weight: 700; color: #0f172a; line-height: 1; }
.stat-card .stat-label { font-size: .78rem; color: #64748b; margin-top: 2px; }

/* ── Table card ──────────────────────────────────────────── */
.table-card {
  background: #fff;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.table-card .table-card-header {
  padding: 14px 18px;
  border-bottom: 1px solid var(--card-border);
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.table > :not(caption) > * > * { padding: .55rem .75rem; }
.table thead th {
  background: #f8fafc;
  font-size: .78rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: #64748b;
  border-bottom: 1px solid var(--card-border);
  white-space: nowrap;
}
.table tbody tr:hover td { background: #f8fafc; }
.table tbody td { font-size: .875rem; vertical-align: middle; border-color: #f1f5f9; }

/* ── Status badges ───────────────────────────────────────── */
.badge-izmenjeno   { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }
.badge-bez-promena { background:#dcfce7; color:#166534; border:1px solid #86efac; }
.badge-nije-preuzeto { background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; }
.badge-greska      { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

/* ── Tip badges ──────────────────────────────────────────── */
.badge-osnovno { background:#dbeafe; color:#1e40af; border:1px solid #93c5fd; }
.badge-izmena  { background:#ffedd5; color:#9a3412; border:1px solid #fdba74; }
.badge-dopuna  { background:#e0f2fe; color:#075985; border:1px solid #7dd3fc; }
.badge-ostalo  { background:#f1f5f9; color:#475569; border:1px solid #94a3b8; }

/* ── Diff ────────────────────────────────────────────────── */
.diff-added   { background:#dcfce7 !important; }
.diff-removed { background:#fee2e2 !important; }
.diff-changed { background:#fef9c3 !important; }

/* ── Inline edit ─────────────────────────────────────────── */
.editable {
  cursor: pointer; border-bottom: 1px dashed #cbd5e1;
  display: inline-block; min-width: 40px;
  transition: border-color .15s;
}
.editable:hover { border-bottom-color: var(--accent); background: #eff6ff; border-radius: 3px; }

/* ── Misc ────────────────────────────────────────────────── */
.spin { animation: spin 1s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
code { font-size: .8em; color: #475569; }
.table-sm .table > :not(caption) > * > * { padding: .35rem .55rem; }

/* ── Cron log terminal ───────────────────────────────────── */
.cron-error   { color: #fca5a5; }
.cron-warn    { color: #fde68a; }
.cron-debug   { color: #475569; }
.cron-section { color: #93c5fd; font-weight: 700; }
.cron-new     { color: #6ee7b7; font-weight: 600; }
.cron-skip    { color: #475569; }
.cron-info    { color: #e2e8f0; }.cron-paused  { color: #fde68a; font-style: italic; }
/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 767.98px) {
  .sidebar {
    transform: translateX(calc(-1 * var(--sidebar-width)));
  }
  .sidebar.open { transform: translateX(0); }
  .main-wrapper { margin-left: 0; }
  .topbar-hamburger { display: flex; align-items: center; justify-content: center; }
  .main-content { padding: 16px 16px 28px; }
  .topbar { padding: 0 16px; }
  .stat-card { padding: 14px 16px; }
  .sidebar-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 1039;
  }
  .sidebar.open ~ .sidebar-overlay,
  body.sidebar-open .sidebar-overlay { display: block; }
}
</style>
</head>
<body>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <a href="<?= $base ?>index.php" class="sidebar-brand">
    <span class="brand-icon"><i class="bi bi-ballot-fill"></i></span>
    <span>GM Monitor</span>
  </a>

  <div class="nav-section-label">Navigacija</div>
  <ul class="sidebar-nav">
    <?php foreach ($navItems as $nav):
        $isActive = ($nav['id'] === $activeNav);
        $badge    = ($nav['badge'] ?? 0);
    ?>
    <li>
      <a href="<?= htmlspecialchars($nav['href']) ?>" class="<?= $isActive ? 'active' : '' ?>">
        <i class="bi <?= $nav['icon'] ?>"></i>
        <?= htmlspecialchars($nav['label']) ?>
        <?php if ($badge > 0): ?>
          <span class="nav-badge"><?= $badge ?></span>
        <?php endif; ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>

  <div class="sidebar-footer">
    <?php if ($lastCron): ?>
      <i class="bi bi-clock-history me-1"></i>Posl. provera:<br>
      <span title="<?= htmlspecialchars($lastCron) ?>"><?= _timeAgo($lastCron) ?></span>
    <?php else: ?>
      <i class="bi bi-dash-circle me-1"></i>Još nije pokrenuto
    <?php endif; ?>
  </div>
</aside>

<!-- Main wrapper -->
<div class="main-wrapper">

  <!-- Topbar -->
  <header class="topbar">
    <button class="topbar-hamburger" onclick="toggleSidebar()" title="Meni">
      <i class="bi bi-list"></i>
    </button>
    <h1 class="topbar-title"><?= htmlspecialchars($pageTitle) ?></h1>
    <div class="topbar-actions">
      <?php if ($changesCount > 0): ?>
        <span class="badge" style="background:#dc2626;font-size:.8rem;padding:5px 9px;">
          <i class="bi bi-exclamation-circle me-1"></i><?= $changesCount ?> izmena
        </span>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!empty($breadcrumbs)): ?>
  <div class="page-header">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">
          <a href="<?= $base ?>index.php" class="text-decoration-none">
            <i class="bi bi-house me-1"></i>Dashboard
          </a>
        </li>
        <?php foreach ($breadcrumbs as $i => [$label, $href]): ?>
          <?php if ($href && $i < count($breadcrumbs) - 1): ?>
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($href) ?>" class="text-decoration-none"><?= htmlspecialchars($label) ?></a></li>
          <?php else: ?>
            <li class="breadcrumb-item active"><?= htmlspecialchars($label) ?></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ol>
    </nav>
  </div>
  <?php endif; ?>

  <!-- Main content (caller fills this) -->
  <main class="main-content">
<?php
} // end layout_start()


function layout_end(string $extraJs = ''): void {
    $base = _layout_base(); ?>
  </main>
</div><!-- .main-wrapper -->

<!-- ── Reset modal ─────────────────────────────────────── -->
<div class="modal fade" id="resetModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="border-bottom-color:#fca5a5;">
        <h5 class="modal-title text-danger d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill"></i>Resetovanje baze i storage
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex gap-3 align-items-start">
          <i class="bi bi-exclamation-triangle-fill text-danger flex-shrink-0" style="font-size:1.6rem;margin-top:2px;"></i>
          <div>
            <p class="fw-semibold mb-2" style="font-size:.9rem;">Ova akcija će <strong>nepovratno</strong> obrisati:</p>
            <ul class="mb-1" style="font-size:.875rem;">
              <li>Sve redove iz tabela <code>rik_fajlovi</code>, <code>rik_fajlovi_verzije</code>, <code>glasacka_mesta</code></li>
              <li>Sve preuzete fajlove iz <code>storage/fajlovi/</code></li>
              <li>Cron log i historiju pokretanja</li>
            </ul>
            <p class="text-muted mb-0" style="font-size:.82rem;">
              Tabele <code>opstine</code> i <code>gradovi</code> neće biti dirnute.
            </p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Otkaži</button>
        <button type="button" class="btn btn-danger btn-sm" id="resetConfirmBtn" onclick="resetDB()">
          <i class="bi bi-trash me-1"></i>Potvrdi reset
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
  <div id="toast" class="toast align-items-center text-white border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="toastBody"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const _cronBase = '<?= $base ?>';

/* ── Sidebar (mobile) ─────────────────────────────────────── */
function toggleSidebar() {
  const open = $('#sidebar').toggleClass('open').hasClass('open');
  $('body').toggleClass('sidebar-open', open);
}
function closeSidebar() {
  $('#sidebar').removeClass('open');
  $('body').removeClass('sidebar-open');
}

async function resetDB() {
  const $btn = $('#resetConfirmBtn');
  $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Brišem...');
  try {
    const json = await $.ajax({
      url        : _cronBase + 'api.php',
      method     : 'POST',
      contentType: 'application/json',
      dataType   : 'json',
      data       : JSON.stringify({ action: 'reset_db' })
    });
    if (!json.success) throw new Error(json.error);
    location.reload();
  } catch (e) {
    $btn.prop('disabled', false).html('<i class="bi bi-trash me-1"></i>Potvrdi reset');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('resetModal')).hide();
    showToast('Greška: ' + (e.responseJSON?.error || e.message || e), 'danger');
  }
}

/* ── Toast ────────────────────────────────────────────────── */
function showToast(msg, type = 'success') {
  const el = document.getElementById('toast');
  el.className = `toast align-items-center text-white border-0 bg-${type}`;
  $('#toastBody').text(msg);
  bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 }).show();
}

/* ── Misc helpers ─────────────────────────────────────────── */
function escHtml(s) {
  if (s == null) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function escAttr(s) {
  if (s == null) return '';
  return String(s).replace(/"/g,'&quot;').replace(/</g,'&lt;');
}
<?= $extraJs ?>
</script>
</body>
</html>
<?php
} // end layout_end()

/* ── Shared PHP helpers ──────────────────────────────────── */

function _timeAgo(string $dateStr): string {
    $d    = new DateTime($dateStr);
    $diff = (new DateTime())->getTimestamp() - $d->getTimestamp();
    if ($diff < 60)    return 'Maloprije';
    if ($diff < 3600)  return floor($diff/60) . ' min';
    if ($diff < 86400) return floor($diff/3600) . 'h';
    return floor($diff/86400) . ' dana';
}

function statusBadgeHtml(string $status, ?string $tooltip = null): string {
    $cfg = [
        'izmenjeno'     => ['badge-izmenjeno',    'bi-exclamation-triangle-fill', 'Izmenjeno'],
        'bez_promena'   => ['badge-bez-promena',   'bi-check-circle-fill',        'Bez promena'],
        'nije_preuzeto' => ['badge-nije-preuzeto', 'bi-download',                 'Nije preuzeto'],
        'greska'        => ['badge-greska',        'bi-x-circle-fill',            'Greška'],
    ];
    [$cls, $icon, $label] = $cfg[$status] ?? $cfg['nije_preuzeto'];
    $t = $tooltip ? ' title="' . htmlspecialchars($tooltip) . '"' : '';
    return "<span class=\"badge rounded-pill {$cls}\"{$t}><i class=\"bi {$icon} me-1\"></i>{$label}</span>";
}

function tipBadgeHtml(string $tip): string {
    return "<span class=\"badge rounded-pill badge-{$tip}\">" . htmlspecialchars($tip) . "</span>";
}
