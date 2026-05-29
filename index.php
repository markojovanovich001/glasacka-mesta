<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

$db           = getDB();
$totalFajlova = (int)($db->query("SELECT COUNT(*) c FROM rik_fajlovi WHERE aktivan=1")->fetch_assoc()['c'] ?? 0);
$totalChanges = (int)($db->query("SELECT COUNT(*) c FROM rik_fajlovi WHERE has_changes=1 AND aktivan=1")->fetch_assoc()['c'] ?? 0);
$totalGm      = (int)($db->query("SELECT COUNT(*) c FROM glasacka_mesta")->fetch_assoc()['c'] ?? 0);
$totalGreske  = (int)($db->query("SELECT COUNT(*) c FROM rik_fajlovi WHERE last_error IS NOT NULL AND aktivan=1")->fetch_assoc()['c'] ?? 0);

// Pre-select filter from URL (e.g. ?filter=izmenjeno)
$preFilter = htmlspecialchars($_GET['filter'] ?? '');

layout_start('Dashboard', 'dashboard');
?>

<!-- ── Stat cards ─────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#eff6ff;color:#3b82f6;"><i class="bi bi-files"></i></div>
      <div>
        <div class="stat-value"><?= $totalFajlova ?></div>
        <div class="stat-label">Fajlova na listi</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fffbeb;color:#d97706;"><i class="bi bi-bell-fill"></i></div>
      <div>
        <div class="stat-value" style="color:<?= $totalChanges > 0 ? '#d97706' : '' ?>"><?= $totalChanges ?></div>
        <div class="stat-label">Nepregledane izmene</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-geo-alt-fill"></i></div>
      <div>
        <div class="stat-value" style="color:#16a34a;"><?= number_format($totalGm, 0, ',', '.') ?></div>
        <div class="stat-label">Parsovano GM</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef2f2;color:#dc2626;"><i class="bi bi-x-circle-fill"></i></div>
      <div>
        <div class="stat-value" style="color:<?= $totalGreske > 0 ? '#dc2626' : '' ?>"><?= $totalGreske ?></div>
        <div class="stat-label">Greška pri preuzimanju</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Table card ─────────────────────────────────────────── -->
<div class="table-card">
  <div class="table-card-header">
    <i class="bi bi-table text-secondary"></i>
    <span class="fw-semibold me-auto">Spisak fajlova</span>

    <input type="text" id="filterSearch" class="form-control form-control-sm"
           style="max-width:220px" placeholder="Pretraži naziv...">

    <select id="filterStatus" class="form-select form-select-sm" style="max-width:150px">
      <option value="">Svi statusi</option>
      <option value="izmenjeno"     <?= $preFilter==='izmenjeno'      ? 'selected' : '' ?>>Izmenjeno</option>
      <option value="bez_promena"   <?= $preFilter==='bez_promena'    ? 'selected' : '' ?>>Bez promena</option>
      <option value="nije_preuzeto" <?= $preFilter==='nije_preuzeto'  ? 'selected' : '' ?>>Nije preuzeto</option>
      <option value="greska"        <?= $preFilter==='greska'         ? 'selected' : '' ?>>Greška</option>
    </select>

    <span class="text-muted small ms-1 text-nowrap">
      <span id="rowCount">0</span> lok.
    </span>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="mainTable">
      <thead>
        <tr>
          <th class="ps-3">Naziv</th>
          <th>Opština / Grad</th>
          <th class="text-center">GM</th>
          <th>Poslednja provera</th>
          <th>Status</th>
          <th class="pe-3" style="width:120px"></th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <tr>
          <td colspan="6" class="text-center py-5 text-muted">
            <span class="spinner-border spinner-border-sm me-2"></span>Učitavanje...
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php
$js = <<<'JS'
// ─── State ──────────────────────────────────────────────────
let allRows = [];

// ─── Init ───────────────────────────────────────────────────
$(function() {
  loadStatus();

  $('#filterSearch').on('input',  applyFilters);
  $('#filterStatus').on('change', applyFilters);
});

// ─── Load ───────────────────────────────────────────────────
async function loadStatus() {
  try {
    const json = await $.getJSON('api.php', { action: 'status' });
    if (!json.success) throw new Error(json.error);
    allRows = json.data;
    applyFilters();
  } catch (e) {
    showToast('Greška pri učitavanju: ' + (e.responseJSON?.error || e.message || e), 'danger');
  }
}

// ─── Filters ────────────────────────────────────────────────
function applyFilters() {
  const search   = $('#filterSearch').val().toLowerCase().trim();
  const statusF  = $('#filterStatus').val();

  const filtered = allRows.filter(row => {
    const ok1 = !search ||
      row.naziv.toLowerCase().includes(search) ||
      row.naziv_clean.toLowerCase().includes(search) ||
      (row.opstina_naziv || '').toLowerCase().includes(search) ||
      (row.grad_naziv    || '').toLowerCase().includes(search);
    const ok2 = !statusF || getStatus(row) === statusF;
    return ok1 && ok2;
  });

  renderTable(filtered);
}

function getStatus(row) {
  if (row.last_error && !row.parsed)   return 'greska';
  if (!row.poslednja_provera)          return 'nije_preuzeto';
  if (parseInt(row.has_changes) === 1) return 'izmenjeno';
  return 'bez_promena';
}

// ─── Grupisanje po naziv_clean ───────────────────────────────
function groupRows(rows) {
  const map = {};
  rows.forEach(row => {
    const key = row.naziv_clean.toLowerCase();
    if (!map[key]) map[key] = { main: null, kids: [] };
    if ((row.tip === 'osnovno' || row.tip === 'ostalo') && !map[key].main) {
      map[key].main = row;
    } else {
      map[key].kids.push(row);
    }
  });
  return Object.entries(map)
    .sort(([a], [b]) => a.localeCompare(b, 'sr'))
    .map(([, g]) => {
      if (!g.main && g.kids.length) { g.main = g.kids.shift(); }
      return g;
    });
}

// ─── Render ─────────────────────────────────────────────────
function renderTable(rows) {
  const groups = groupRows(rows);
  $('#rowCount').text(groups.length);
  const $tbody = $('#tableBody');

  if (!groups.length) {
    $tbody.html('<tr><td colspan="6" class="text-center py-5 text-muted">Nema rezultata</td></tr>');
    return;
  }

  const statusCfg = {
    izmenjeno:    ['badge-izmenjeno',     'bi-exclamation-triangle-fill', 'Izmenjeno'],
    bez_promena:  ['badge-bez-promena',   'bi-check-circle-fill',         'Bez promena'],
    nije_preuzeto:['badge-nije-preuzeto', 'bi-download',                  'Nije preuzeto'],
    greska:       ['badge-greska',        'bi-x-circle-fill',             'Greška'],
  };

  function buildRow(row, isChild) {
    const status  = getStatus(row);
    const [bCls,bIcon,bLabel] = statusCfg[status] || statusCfg['nije_preuzeto'];
    const tooltipAttr = (status==='greska' && row.last_error) ? ` title="${escAttr(row.last_error)}"` : '';
    const statusBadge = `<span class="badge rounded-pill ${bCls}"${tooltipAttr}><i class="bi ${bIcon} me-1"></i>${bLabel}</span>`;
    const lokacija    = escHtml(row.opstina_naziv || row.grad_naziv || '—');
    const provera     = row.poslednja_provera
      ? `<span title="${escAttr(row.poslednja_provera)}" class="text-muted small">${timeAgo(row.poslednja_provera)}</span>`
      : '<span class="text-muted small">Nikad</span>';
    const gmBadge     = parseInt(row.gm_count) > 0
      ? `<span class="badge" style="background:#e2e8f0;color:#374151;font-weight:600;">${parseInt(row.gm_count).toLocaleString('sr')}</span>`
      : '<span class="text-muted">—</span>';
    const prefix   = isChild ? '<span class="text-muted me-1" style="font-size:.85rem;">└─</span>' : '';
    const label    = isChild ? escHtml(row.naziv) : `<span class="fw-semibold">${escHtml(row.naziv_clean)}</span>`;
    const nameCell = `<td class="${isChild ? '' : 'ps-3'}" style="${isChild ? 'padding-left:2.2rem!important;' : 'padding-left:.75rem;'}max-width:310px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escAttr(row.naziv)}">${prefix}${label}</td>`;

    return `<tr data-fajl-id="${row.id}">
      ${nameCell}
      <td class="text-muted small">${isChild ? '' : lokacija}</td>
      <td class="text-center">${isChild ? '' : gmBadge}</td>
      <td>${provera}</td>
      <td>${statusBadge}</td>
      <td class="pe-3 text-end">
        <a href="pages/detail.php?id=${row.id}" class="btn btn-sm btn-outline-primary py-0 px-2">
          <i class="bi bi-eye me-1"></i>Detalji
        </a>
      </td>
    </tr>`;
  }

  let html = '';
  groups.forEach(({ main, kids }) => {
    if (!main) return;
    html += buildRow(main, false);
    kids.forEach(kid => { html += buildRow(kid, true); });
  });
  $tbody.html(html || '<tr><td colspan="6" class="text-center py-5 text-muted">Nema rezultata</td></tr>');
}

function timeAgo(dateStr) {
  const diff = Math.floor((new Date() - new Date(dateStr)) / 1000);
  if (diff < 60)    return 'Maloprije';
  if (diff < 3600)  return Math.floor(diff/60) + ' min';
  if (diff < 86400) return Math.floor(diff/3600) + 'h';
  return Math.floor(diff/86400) + ' dana';
}
JS;

layout_end($js);
