<?php
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers.php';
require_once dirname(__DIR__) . '/layout.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: ../index.php'); exit; }

$db = getDB();

$stmt = $db->prepare(
    'SELECT f.*, o.name opstina_naziv, g.name grad_naziv
     FROM rik_fajlovi f
     LEFT JOIN opstine o ON o.id = f.opstina_id
     LEFT JOIN gradovi g ON g.id = f.grad_id
     WHERE f.id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$fajl = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fajl) { header('Location: ../index.php'); exit; }

$stmt = $db->prepare(
    'SELECT * FROM rik_fajlovi_verzije WHERE fajl_id=? ORDER BY detected_at DESC, id DESC LIMIT 1'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$novaVerzija = $stmt->get_result()->fetch_assoc();
$stmt->close();

$lokacija = $fajl['opstina_naziv'] ?? $fajl['grad_naziv'] ?? 'Nepoznato';
$pdfPath  = $novaVerzija['pdf_path'] ?? '';
$pdfExists = $pdfPath && file_exists(dirname(__DIR__) . '/' . $pdfPath);

// Za izmena/dopuna fajlove: pronađi osnovni (parent) fajl iste opštine/grada
$roditeljFajl = null;
$gmFajlId     = $id; // ID koji se koristi za GM listu (parent ako postoji)
// Prioritet: ručno definisan roditelju fajl
if (!empty($fajl['roditelj_fajl_id'])) {
    $stmtR = $db->prepare("SELECT id, naziv FROM rik_fajlovi WHERE id=?");
    $stmtR->bind_param('i', $fajl['roditelj_fajl_id']);
    $stmtR->execute();
    $roditeljFajl = $stmtR->get_result()->fetch_assoc();
    $stmtR->close();
    if ($roditeljFajl) $gmFajlId = (int)$roditeljFajl['id'];
} elseif (in_array($fajl['tip'], ['izmena', 'dopuna'], true)) {
    if (!empty($fajl['opstina_id'])) {
        $stmtR = $db->prepare(
            "SELECT id, naziv FROM rik_fajlovi
             WHERE opstina_id=? AND tip='osnovno' AND aktivan=1
             ORDER BY id DESC LIMIT 1"
        );
        $stmtR->bind_param('i', $fajl['opstina_id']);
    } elseif (!empty($fajl['grad_id'])) {
        $stmtR = $db->prepare(
            "SELECT id, naziv FROM rik_fajlovi
             WHERE grad_id=? AND tip='osnovno' AND aktivan=1
             ORDER BY id DESC LIMIT 1"
        );
        $stmtR->bind_param('i', $fajl['grad_id']);
    }
    if (isset($stmtR)) {
        $stmtR->execute();
        $roditeljFajl = $stmtR->get_result()->fetch_assoc();
        $stmtR->close();
        if ($roditeljFajl) {
            $gmFajlId = (int)$roditeljFajl['id'];
        }
    }
}

// Opcije za lokacija modal
$gradoviAll = [];
$res = $db->query('SELECT id, name FROM gradovi ORDER BY name');
while ($row = $res->fetch_assoc()) $gradoviAll[] = $row;

$opstineAll = [];
$res = $db->query('SELECT id, name FROM opstine ORDER BY name');
while ($row = $res->fetch_assoc()) $opstineAll[] = $row;

$osnovniFajlovi = [];
$res = $db->query("SELECT id, naziv FROM rik_fajlovi WHERE tip='osnovno' AND aktivan=1 ORDER BY naziv_clean");
while ($row = $res->fetch_assoc()) $osnovniFajlovi[] = $row;

layout_start(
    $fajl['naziv'],
    'dashboard',
    [[$fajl['naziv'], null]]
);
?>

<!-- ── Meta bar ─────────────────────────────────────────── -->
<div class="table-card mb-3">
  <div class="table-card-header flex-wrap gap-2">

    <!-- Left: meta pills -->
    <div class="d-flex align-items-center gap-2 flex-wrap me-auto">
      <!-- Status -->
      <?php if ($fajl['has_changes']): ?>
        <?= statusBadgeHtml('izmenjeno') ?>
      <?php elseif ($novaVerzija): ?>
        <?= statusBadgeHtml('bez_promena') ?>
      <?php else: ?>
        <?= statusBadgeHtml('nije_preuzeto') ?>
      <?php endif; ?>

      <!-- Lokacija -->
      <span class="badge rounded-pill"
            style="background:#f1f5f9;color:#334155;font-weight:500;font-size:.78rem;gap:4px;">
        <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($lokacija) ?>
      </span>

      <!-- Vreme -->
      <?php if ($novaVerzija): ?>
        <span class="badge rounded-pill"
              style="background:#f1f5f9;color:#334155;font-weight:500;font-size:.78rem;"
              title="<?= htmlspecialchars($novaVerzija['detected_at']) ?>">
          <i class="bi bi-clock me-1"></i><?= htmlspecialchars($novaVerzija['detected_at']) ?>
        </span>

        <!-- Format fajla -->
        <span class="badge rounded-pill"
              style="background:#eff6ff;color:#2563eb;font-weight:600;font-size:.78rem;">
          <i class="bi bi-file-earmark-word me-1"></i><?= strtoupper(htmlspecialchars($novaVerzija['file_ext'] ?? '')) ?>
        </span>

        <!-- Hash -->
        <span class="badge rounded-pill font-monospace"
              style="background:#f8fafc;color:#64748b;font-weight:400;font-size:.74rem;"
              title="SHA256: <?= htmlspecialchars($novaVerzija['file_hash'] ?? '') ?>">
          <?= htmlspecialchars(substr($novaVerzija['file_hash'] ?? '', 0, 12)) ?>…
        </span>

        <!-- PDF status -->
        <?php if ($pdfExists): ?>
          <span class="badge rounded-pill"
                style="background:#f0fdf4;color:#16a34a;font-weight:500;font-size:.78rem;">
            <i class="bi bi-filetype-pdf me-1"></i>PDF dostupan
          </span>
        <?php else: ?>
          <span class="badge rounded-pill"
                style="background:#fffbeb;color:#b45309;font-weight:500;font-size:.78rem;">
            <i class="bi bi-exclamation-triangle me-1"></i>PDF nedostupan
          </span>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Right: actions -->
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalLokacija"
              title="Izmeni grad, opštinu i rut dokument">
        <i class="bi bi-pencil me-1"></i>Edit
      </button>
      <?php if ($novaVerzija && ($novaVerzija['conv_path'] ?? $novaVerzija['file_path'] ?? '')): ?>
        <a href="../<?= htmlspecialchars($novaVerzija['conv_path'] ?? $novaVerzija['file_path']) ?>"
           class="btn btn-sm btn-outline-secondary" download>
          <i class="bi bi-download me-1"></i>Preuzmi
        </a>
      <?php endif; ?>
      <?php if ($fajl['has_changes']): ?>
        <button class="btn btn-sm btn-success" id="btnPrihvati">
          <i class="bi bi-check2-circle me-1"></i>Prihvati promenu
        </button>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ── Split layout: PDF (levo) + Podaci u bazi (desno) ──── -->
<div class="row g-3">

  <!-- LEFT: PDF prikaz -->
  <div class="col-lg-6">
    <div class="table-card h-100">
      <div class="table-card-header">
        <i class="bi bi-filetype-pdf text-danger"></i>
        <span class="fw-semibold">PDF prikaz</span>
        <?php if ($novaVerzija && !empty($novaVerzija['detected_at'])): ?>
          <span class="text-muted small ms-auto"><?= htmlspecialchars($novaVerzija['detected_at']) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($pdfExists): ?>
        <iframe src="../<?= htmlspecialchars($pdfPath) ?>"
                style="width:100%;height:78vh;border:none;border-radius:0 0 10px 10px;display:block;"></iframe>
      <?php elseif ($pdfPath): ?>
        <div class="p-4 text-center text-muted">
          <i class="bi bi-file-earmark-pdf" style="font-size:3rem;opacity:.3;display:block;margin:0 auto 12px;"></i>
          <p class="mb-2">PDF fajl nije pronađen na disku</p>
          <small><code><?= htmlspecialchars($pdfPath) ?></code></small>
        </div>
      <?php else: ?>
        <div class="p-4 text-center text-muted">
          <i class="bi bi-cloud-download" style="font-size:3rem;opacity:.3;display:block;margin:0 auto 12px;"></i>
          <p class="mb-0">Fajl još nije preuzet ili konvertovan u PDF.<br>
          <span class="small">Pokrenite proveru sa dashboarda.</span></p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RIGHT: Podaci u bazi -->
  <div class="col-lg-6">
    <div class="table-card">
      <div class="table-card-header">
        <i class="bi bi-geo-alt text-secondary"></i>
        <span class="fw-semibold me-auto">Glasačka mesta
          <?php if ($roditeljFajl): ?>
            <small class="text-muted fw-normal">– iz: <a href="detail.php?id=<?= $roditeljFajl['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($roditeljFajl['naziv']) ?></a></small>
          <?php endif; ?>
        </span>
        <div class="input-group input-group-sm" style="max-width:200px;">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" id="gmSearch" class="form-control" placeholder="Pretraži GM...">
        </div>
        <button class="btn btn-sm btn-outline-success" onclick="addGmForm()"
                title="Dodaj glasačko mesto ručno">
          <i class="bi bi-plus-lg me-1"></i>Dodaj GM
        </button>
        <span class="text-muted small text-nowrap"><span id="gmCount">0</span> GM</span>
      </div>

      <div id="dodaj-gm-forms" class="px-2 pt-2"></div>

      <div id="gmLoading" class="text-center py-5 text-muted">
        <span class="spinner-border spinner-border-sm me-2"></span>Učitavanje...
      </div>

      <div class="table-responsive" id="gmTableWrap" style="display:none;max-height:74vh;overflow-y:auto;">
        <table class="table table-sm align-middle mb-0" id="gmTable">
          <thead>
            <tr>
              <th style="width:28px;"></th>
              <th class="ps-2" style="width:58px;">Br. GM</th>
              <th style="min-width:130px;">Naziv</th>
              <th style="min-width:130px;">Adresa</th>
              <th class="text-center" style="width:48px;">Obriši</th>
            </tr>
          </thead>
          <tbody id="gmTbody"></tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- .row -->

<!-- ── Modal: izmeni lokaciju ────────────────────────────────── -->
<div class="modal fade" id="modalLokacija" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-geo-alt me-2"></i>Izmeni lokaciju</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Ručno podesite grad, opštinu i/ili rut dokument za ovaj fajl.<br>
          <span class="text-secondary">Kliknite <i class="bi bi-x-lg"></i> pored selekta da poništite vrednost.</span>
        </p>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Grad</label>
            <div class="input-group">
              <select class="form-select" id="lokGrad">
                <option value="">— bez grada —</option>
                <?php foreach ($gradoviAll as $g): ?>
                  <option value="<?= (int)$g['id'] ?>"
                    <?= ($fajl['grad_id'] !== null && (int)$fajl['grad_id'] === (int)$g['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($g['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-outline-secondary" type="button" id="btnClearGrad" title="Ukloni grad">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Opština</label>
            <div class="input-group">
              <select class="form-select" id="lokOpstina">
                <option value="">— bez opštine —</option>
                <?php foreach ($opstineAll as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"
                    <?= ($fajl['opstina_id'] !== null && (int)$fajl['opstina_id'] === (int)$o['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($o['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-outline-secondary" type="button" id="btnClearOpstina" title="Ukloni opštinu">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">
              Rut dokument
              <small class="text-muted fw-normal">– za izmene/dopune koje nisu auto-matchovane</small>
            </label>
            <div class="input-group">
              <select class="form-select" id="lokRutFajl">
                <option value="">— automatski (preporučeno) —</option>
                <?php foreach ($osnovniFajlovi as $of): ?>
                  <option value="<?= (int)$of['id'] ?>"
                    <?= (!empty($fajl['roditelj_fajl_id']) && (int)$fajl['roditelj_fajl_id'] === (int)$of['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($of['naziv']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-outline-secondary" type="button" id="btnClearRutFajl" title="Ukloni rut dokument">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
            <div class="form-text">Ručno povežite ovaj fajl sa osnovnim dokumentom iste opštine ako auto-detekcija nije ispravna.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Otkaži</button>
        <button class="btn btn-primary" id="btnSacuvajLokaciju">
          <i class="bi bi-save me-1"></i>Sačuvaj
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$jsHasChanges = (int)$fajl['has_changes'];
$jsGmFajlId   = $gmFajlId;
$js = <<<JS
const FAJL_ID     = {$id};
const GM_FAJL_ID  = {$jsGmFajlId};  // Fajl ID za GM listu (parent za izmena/dopuna)
const HAS_CHANGES = {$jsHasChanges};

let allGmRows       = [];   // flat GM list
let _gmFormCounter  = 0;    // sequencer za ID inline GM formi

$(function() {
  loadGmList();
  $('#gmSearch').on('input', filterGmTable);
  $('#btnPrihvati').on('click', prihvatiPromenu);

  // ── Event delegation: expand / delete GM (statički tbody) ──────────────
  $('#gmTbody').on('click', '.expand-btn', function() {
    const \$tr = \$(this).closest('tr.gm-row');
    togglePodrucja(this, \$tr.data('gm-id'), \$tr.data('gm-broj'));
  });
  $('#gmTbody').on('click', '.gm-delete-btn', function() {
    const \$tr = \$(this).closest('tr.gm-row');
    deleteGm(\$tr.data('gm-id'), this);
  });

  // ── Event delegation: područja dugmad ──────────────────────────────────
  // toggle single-add panel
  $('#gmTableWrap').on('click', '.btn-toggle-add-p', function() {
    const gmId  = \$(this).data('gm-id');
    const \$p    = \$('#add-p-single-' + gmId);
    const \$bulk = \$('#add-p-bulk-' + gmId);
    \$bulk.hide();
    \$p.toggle();
    if (\$p.is(':visible')) \$p.find('.single-p-naziv').focus();
  });
  // toggle bulk panel
  $('#gmTableWrap').on('click', '.btn-bulk-p', function(e) {
    e.preventDefault();
    const gmId  = \$(this).data('gm-id');
    const \$p    = \$('#add-p-single-' + gmId);
    const \$bulk = \$('#add-p-bulk-' + gmId);
    \$p.hide();
    \$bulk.toggle();
    if (\$bulk.is(':visible')) \$bulk.find('.bulk-p-textarea').focus();
  });
  // close bulk panel
  $('#gmTableWrap').on('click', '.bulk-cancel-btn', function() {
    const gmId = \$(this).data('gm-id');
    \$('#add-p-bulk-' + gmId).hide();
    \$('#bulk-p-forms-' + gmId).empty();
  });
  // hide single-add panel
  $('#gmTableWrap').on('click', '.single-p-hide-btn', function() {
    const gmId = \$(this).data('gm-id');
    \$('#add-p-single-' + gmId).hide().find('input').val('');
  });
  // save single area
  $('#gmTableWrap').on('click', '.single-p-save-btn', async function() {
    const gmId   = \$(this).data('gm-id');
    const \$panel = \$('#add-p-single-' + gmId);
    const naziv  = \$panel.find('.single-p-naziv').val().trim();
    const broj   = \$panel.find('.single-p-broj').val().trim();
    if (!naziv) { showToast('Naziv je obavezan', 'warning'); return; }
    const \$btn = \$(this).prop('disabled', true);
    try {
      const json = await \$.ajax({
        url: '../api.php', method: 'POST', contentType: 'application/json', dataType: 'json',
        data: JSON.stringify({ action: 'add_podrucje', gm_id: parseInt(gmId), naziv, broj_adrese: broj || null })
      });
      if (!json.success) throw new Error(json.error);
      showToast('Područje dodato', 'success');
      \$panel.hide().find('input').val('');
      appendPodrucjeRow(parseInt(gmId), { id: json.data.id, naziv: json.data.naziv, broj_adrese: json.data.broj_adrese });
    } catch (e) {
      showToast('Greška: ' + (e.responseJSON?.error || e.message || e), 'danger');
    } finally {
      \$btn.prop('disabled', false);
    }
  });
  // parse bulk textarea into individual forms
  $('#gmTableWrap').on('click', '.bulk-parse-btn', function() {
    const gmId   = \$(this).data('gm-id');
    const input  = \$('#add-p-bulk-' + gmId + ' .bulk-p-textarea').val();
    const tokens = tokenizeAddressStr(input);
    if (!tokens.length) { showToast('Nije prona\u0111ena nijedna adresa', 'warning'); return; }
    const \$forms = \$('#bulk-p-forms-' + gmId).empty();
    tokens.forEach((tok, i) => {
      const fid = gmId + '_' + i;
      \$forms.append(
        '<div class="d-flex gap-1 mb-1 align-items-center bulk-entry-form" id="bf-' + fid + '">'
        + '<input type="text" class="form-control form-control-sm bf-naziv" value="' + escAttr(tok.naziv) + '" placeholder="Naziv" style="flex:3;">'
        + '<input type="text" class="form-control form-control-sm bf-broj" value="' + escAttr(tok.broj_adrese) + '" placeholder="Br." style="flex:1;max-width:90px;">'
        + '<button class="btn btn-sm btn-success bulk-form-save-btn" data-gm-id="' + gmId + '" data-fid="' + fid + '" title="Sa\u010duvaj"><i class="bi bi-check-lg"></i></button>'
        + '<button class="btn btn-sm btn-outline-secondary bulk-form-hide-btn" data-fid="' + fid + '" title="Ukloni">×</button>'
        + '</div>'
      );
    });
    if (tokens.length > 1) {
      \$forms.append('<button class="btn btn-sm btn-success mt-1 bulk-save-all-btn" data-gm-id="' + gmId + '">'
        + '<i class="bi bi-save me-1"></i>Sa\u010duvaj sve (' + tokens.length + ')</button>');
    }
  });
  // remove one bulk-form entry
  $('#gmTableWrap').on('click', '.bulk-form-hide-btn', function() {
    \$('#bf-' + \$(this).data('fid')).remove();
  });
  // save one bulk-form entry
  $('#gmTableWrap').on('click', '.bulk-form-save-btn', async function() {
    const gmId  = \$(this).data('gm-id');
    const fid   = \$(this).data('fid');
    const \$row = \$('#bf-' + fid);
    const naziv = \$row.find('.bf-naziv').val().trim();
    const broj  = \$row.find('.bf-broj').val().trim();
    if (!naziv) { showToast('Naziv je obavezan', 'warning'); return; }
    const \$btn = \$(this).prop('disabled', true);
    try {
      const json = await \$.ajax({
        url: '../api.php', method: 'POST', contentType: 'application/json', dataType: 'json',
        data: JSON.stringify({ action: 'add_podrucje', gm_id: parseInt(gmId), naziv, broj_adrese: broj || null })
      });
      if (!json.success) throw new Error(json.error);
      showToast('Sa\u010duvano', 'success');
      \$row.remove();
      appendPodrucjeRow(parseInt(gmId), { id: json.data.id, naziv: json.data.naziv, broj_adrese: json.data.broj_adrese });
    } catch (e) {
      \$btn.prop('disabled', false);
      showToast('Greška: ' + (e.responseJSON?.error || e.message || e), 'danger');
    }
  });
  // save all bulk-form entries
  $('#gmTableWrap').on('click', '.bulk-save-all-btn', async function() {
    const gmId  = \$(this).data('gm-id');
    const \$rows = \$('#bulk-p-forms-' + gmId + ' .bulk-entry-form');
    const \$btn  = \$(this).prop('disabled', true);
    let saved = 0, errors = 0;
    for (const row of \$rows.toArray()) {
      const \$row  = \$(row);
      const naziv = \$row.find('.bf-naziv').val().trim();
      const broj  = \$row.find('.bf-broj').val().trim();
      if (!naziv) continue;
      try {
        const json = await \$.ajax({
          url: '../api.php', method: 'POST', contentType: 'application/json', dataType: 'json',
          data: JSON.stringify({ action: 'add_podrucje', gm_id: parseInt(gmId), naziv, broj_adrese: broj || null })
        });
        if (json.success) {
          \$row.remove();
          appendPodrucjeRow(parseInt(gmId), { id: json.data.id, naziv: json.data.naziv, broj_adrese: json.data.broj_adrese });
          saved++;
        } else { errors++; }
      } catch (e) { errors++; }
    }
    \$btn.prop('disabled', false);
    if (saved)  showToast('Sa\u010duvano ' + saved + ' podru\u010dja', 'success');
    if (errors) showToast('Greška pri ' + errors + ' unosa', 'danger');
    if (!\$('#bulk-p-forms-' + gmId + ' .bulk-entry-form').length) {
      \$('#add-p-bulk-' + gmId).hide();
      \$('#bulk-p-forms-' + gmId).empty();
    }
  });
  // delete area
  $('#gmTableWrap').on('click', '.podrucje-delete-btn', function() {
    deletePodrucje(\$(this).data('pid'), this);
  });

  // ── Inline edit – event delegation za GM i područja ─────────────────────
  $('#gmTbody').on('click', '.editable-gm', startInlineEditGm);
  $('#gmTableWrap').on('click', '.editable-p', startInlineEditPodrucje);

  // ── Lokacija modal ───────────────────────────────────────────────────────
  \$('#btnClearGrad').on('click',    () => \$('#lokGrad').val(''));
  \$('#btnClearOpstina').on('click', () => \$('#lokOpstina').val(''));
  \$('#btnClearRutFajl').on('click', () => \$('#lokRutFajl').val(''));
  \$('#btnSacuvajLokaciju').on('click', saveLokaciju);
});
// ─── Prihvati promenu ────────────────────────────────────────
async function prihvatiPromenu() {
  if (!confirm('Markiraj ovu promenu kao pregledanu?')) return;
  try {
    const json = await \$.ajax({
      url        : '../api.php',
      method     : 'POST',
      contentType: 'application/json',
      dataType   : 'json',
      data       : JSON.stringify({ action: 'prihvati_promenu', fajl_id: FAJL_ID })
    });
    if (!json.success) throw new Error(json.error);
    showToast('Promena prihvaćena', 'success');
    \$('#btnPrihvati').remove();
  } catch (e) {
    showToast('Greška: ' + (e.responseJSON?.error || e.message || e), 'danger');
  }
}

// ─── GM lista ─────────────────────────────────────────────────────────────────
async function loadGmList() {
  \$('#gmLoading').show();
  \$('#gmTableWrap').hide();
  try {
    const json = await \$.getJSON('../api.php', { action: 'gm_list', fajl_id: GM_FAJL_ID });
    if (!json.success) throw new Error(json.error);
    allGmRows = json.data;
    renderGmEditTable(allGmRows);
    \$('#gmLoading').hide();
    \$('#gmTableWrap').show();
  } catch (e) {
    \$('#gmLoading').html('<div class="alert alert-danger m-3">Greška: ' + escHtml(e.message || String(e)) + '</div>');
  }
}

function filterGmTable() {
  const q = \$('#gmSearch').val().toLowerCase().trim();
  renderGmEditTable(!q ? allGmRows : allGmRows.filter(r =>
    (r.naziv  || '').toLowerCase().includes(q) ||
    (r.adresa || '').toLowerCase().includes(q) ||
    r.broj_gm.toLowerCase().includes(q)
  ));
}

// ─── Renderovanje hijerarhijske tabele ─────────────────────────────────────────
function renderGmEditTable(rows) {
  \$('#gmCount').text(rows.length);
  const \$tbody = \$('#gmTbody');

  if (!rows.length) {
    \$tbody.html('<tr><td colspan="5" class="text-center text-muted py-4">'
      + 'Nema GM u bazi &nbsp;'
      + '<button class="btn btn-sm btn-outline-success" id="btnNoGmDodaj">'
      + '<i class="bi bi-plus-lg me-1"></i>Dodaj GM</button></td></tr>');
    \$('#btnNoGmDodaj').one('click', addGmForm);
    return;
  }

  // Svaki GM red: data-gm-id i data-gm-broj na TR, bez inline onclick
  const html = rows.map(row => {
    const safeId    = row.id;
    const sajbr     = escHtml(row.broj_gm);
    const saNaziv   = escHtml(row.naziv  || '');
    const saAdresa  = escHtml(row.adresa || '');
    const saGmBroj  = escAttr(String(row.broj_gm));
    const podrucja  = row.podrucja || [];
    const hasP      = podrucja.length > 0;

    let podrucjaHtml = '';
    if (hasP) {
      let pbody = '';
      podrucja.forEach(p => { pbody += renderPodrucjeRow(p, safeId); });
      podrucjaHtml = '<table class="table table-sm table-bordered mb-1" style="font-size:.82rem;">'
        + '<thead class="table-light"><tr>'
        + '<th>Naziv</th>'
        + '<th style="width:70px;">Br.</th>'
        + '<th class="text-center" style="width:50px;">Obriši</th>'
        + '</tr></thead>'
        + '<tbody id="ptbody-' + safeId + '">' + pbody + '</tbody>'
        + '</table>';
    } else {
      podrucjaHtml = '<p class="text-muted small mb-1">Nema područja</p>';
    }
    // Split dugme: "Dodaj područje" | dropdown "Grupni unos"
    podrucjaHtml +=
      '<div class="d-flex flex-column gap-0 mt-1">'
      + '<div class="btn-group btn-group-sm mb-2">'
        + '<button class="btn btn-outline-primary btn-toggle-add-p" style="font-size:.78rem;" data-gm-id="' + safeId + '">'
          + '<i class="bi bi-plus-lg me-1"></i>Dodaj područje</button>'
        + '<button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" style="font-size:.78rem;"></button>'
        + '<ul class="dropdown-menu"><li>'
          + '<a class="dropdown-item btn-bulk-p" href="#" style="font-size:.85rem;" data-gm-id="' + safeId + '">'
            + '<i class="bi bi-list-ul me-2"></i>Grupni unos</a>'
        + '</li></ul>'
      + '</div>'
      // Panel za jedan unos (sakriven)
      + '<div class="add-p-single-panel p-2 mb-2 bg-light rounded border" id="add-p-single-' + safeId + '" style="display:none;">'
        + '<div class="row g-1 align-items-end">'
          + '<div class="col-sm-5"><label class="d-block small mb-1 fw-semibold">Naziv ulice</label>'
            + '<input type="text" class="form-control form-control-sm single-p-naziv" placeholder="Naziv ulice/lokaliteta"></div>'
          + '<div class="col-sm-3"><label class="d-block small mb-1 fw-semibold">Br. adrese</label>'
            + '<input type="text" class="form-control form-control-sm single-p-broj" placeholder="12-A, 0..."></div>'
          + '<div class="col-sm-4 d-flex gap-1 pt-3">'
            + '<button class="btn btn-sm btn-success single-p-save-btn flex-grow-1" data-gm-id="' + safeId + '">Sačuvaj</button>'
            + '<button class="btn btn-sm btn-outline-secondary single-p-hide-btn" data-gm-id="' + safeId + '" title="Sakrij">×</button>'
          + '</div>'
        + '</div>'
      + '</div>'
      // Panel za grupni unos (sakriven)
      + '<div class="add-p-bulk-panel p-2 mb-2 rounded border border-info" id="add-p-bulk-' + safeId + '" style="display:none;background:rgba(13,202,240,.07);">'
        + '<p class="small fw-semibold mb-1">Grupni unos — paste listu adresa iz Word fajla</p>'
        + '<p class="small text-muted mb-2">Format: <code>Ulica 1, 2, 5-A, Druga ulica 10, 12</code> — comma-separated, brojevi se dodaju uz prethodnu ulicu</p>'
        + '<textarea class="form-control form-control-sm bulk-p-textarea font-monospace" rows="5" style="font-size:.78rem;" placeholder="Paste adrese ovde..."></textarea>'
        + '<div class="d-flex gap-1 mt-2">'
          + '<button class="btn btn-sm btn-primary bulk-parse-btn" data-gm-id="' + safeId + '">'
            + '<i class="bi bi-lightning me-1"></i>Parsiraj i prikaži forme</button>'
          + '<button class="btn btn-sm btn-outline-secondary bulk-cancel-btn" data-gm-id="' + safeId + '">Zatvori</button>'
        + '</div>'
        + '<div class="bulk-p-forms mt-3" id="bulk-p-forms-' + safeId + '"></div>'
      + '</div>'
      + '</div>';  // close d-flex flex-column

    return (
      '<tr class="gm-row" data-gm-id="' + safeId + '" data-gm-broj="' + saGmBroj + '">'
      + '<td class="text-center pe-0">'
        + '<button class="btn btn-link btn-sm py-0 px-1 expand-btn" title="Sakrij/prikaži područja">'
          + '<i class="bi bi-chevron-down"></i>'
        + '</button>'
      + '</td>'
      + '<td class="ps-1 fw-bold text-center">' + sajbr + '</td>'
      + '<td><span class="editable-gm" data-gm-id="' + safeId + '" data-field="naziv">'
          + (saNaziv || '<span class="text-muted fst-italic small">—</span>') + '</span></td>'
      + '<td><span class="editable-gm" data-gm-id="' + safeId + '" data-field="adresa">'
          + (saAdresa || '<span class="text-muted fst-italic small">—</span>') + '</span></td>'
      + '<td class="text-center">'
        + '<button class="btn btn-sm btn-outline-danger py-0 px-1 gm-delete-btn" title="Obriši GM">'
          + '<i class="bi bi-trash"></i>'
        + '</button>'
      + '</td>'
      + '</tr>'
      // Red sa područjima – vidljiv odmah (bez d-none)
      + '<tr class="podrucja-row" id="pr-' + safeId + '">'
        + '<td></td>'
        + '<td colspan="4" class="py-1 ps-3 pe-2">'
          + '<div id="pd-' + safeId + '" class="pb-1">' + podrucjaHtml + '</div>'
        + '</td>'
      + '</tr>'
    );
  }).join('');

  \$tbody.html(html);
}

// ─── Toggle za područja ───────────────────────────────────────────────────────
// Sada samo toggle vidljivosti (podaci su već u redu)
async function togglePodrucja(btn, gmId, gmBroj) {
  const \$row  = \$('#pr-' + gmId);
  const \$icon = \$(btn).find('i');
  const open  = \$row.hasClass('d-none');

  if (!open) {
    // Zatvori
    \$row.addClass('d-none');
    \$icon.attr('class', 'bi bi-chevron-right');
    return;
  }

  // Otvori (bez dohvatanja – podaci su već renderovani pri inicijalnom učitavanju)
  \$row.removeClass('d-none');
  \$icon.attr('class', 'bi bi-chevron-down');
}

function renderPodrucja(container, gmId, gmBroj, data) {
  const { podrucja } = data;
  const saGmId  = parseInt(gmId);
  let html = '';

  html += '<table class="table table-sm table-bordered mb-1" style="font-size:.82rem;">';
  html += '<thead class="table-light"><tr>'
    + '<th>Naziv</th>'
    + '<th style="width:70px;">Br.</th>'
    + '<th class="text-center" style="width:50px;">Obriši</th>'
    + '</tr></thead><tbody id="ptbody-' + saGmId + '">';

  if (podrucja.length === 0) {
    html += '<tr><td colspan="3" class="text-muted text-center">Nema područja</td></tr>';
  }

  podrucja.forEach(p => {
    html += renderPodrucjeRow(p, saGmId);
  });

  html += '</tbody></table>';
  \$(container).html(html);
}

function renderPodrucjeRow(p, gmId) {
  const pid    = p.id;
  const saNaz  = escHtml(p.naziv || '');
  const saBroj = escHtml(p.broj_adrese || '');
  return (
    '<tr class="podrucje-row" id="prow-' + pid + '">'
    + '<td><span class="editable-p" data-pid="' + pid + '" data-field="naziv">' + (saNaz  || '<span class="text-muted">—</span>') + '</span></td>'
    + '<td><span class="editable-p" data-pid="' + pid + '" data-field="broj">'  + (saBroj || '<span class="text-muted">—</span>') + '</span></td>'
    + '<td class="text-center">'
      + '<button class="btn btn-xs btn-outline-danger py-0 px-1 podrucje-delete-btn" style="font-size:.72rem;"'
        + ' title="Obriši područje" data-pid="' + pid + '">'
        + '<i class="bi bi-trash"></i>'
      + '</button>'
    + '</td>'
    + '</tr>'
  );
}

// ─── Inline edit – GM polje ───────────────────────────────────────────────────
function startInlineEditGm(e) {
  const \$span = \$(e.currentTarget);
  const gmId  = \$span.data('gm-id');
  const field = \$span.data('field');
  const orig  = \$span.data('orig') !== undefined
    ? \$span.data('orig')
    : (\$span.text().trim() === '—' ? '' : \$span.text().trim());

  const \$input = \$('<input>').attr('type', 'text').val(orig).addClass('form-control form-control-sm');
  \$span.replaceWith(\$input);
  \$input[0].focus();

  async function save() {
    const val    = \$input.val().trim();
    const \$sp   = \$('<span>').addClass('editable-gm')
      .attr({ 'data-gm-id': gmId, 'data-field': field, 'data-orig': val })
      .html(val ? escHtml(val) : '<span class="text-muted fst-italic small">—</span>');
    \$input.replaceWith(\$sp);
    try {
      const json = await \$.ajax({
        url        : '../api.php',
        method     : 'POST',
        contentType: 'application/json',
        dataType   : 'json',
        data       : JSON.stringify({ action: 'update_gm', id: parseInt(gmId), polje: field, vrednost: val })
      });
      if (!json.success) throw new Error(json.error);
      // Prikaži vrednost vraćenu sa servera (latinizovanu)
      const saved = json.data?.vrednost ?? val;
      \$sp.attr('data-orig', saved)
         .html(saved ? escHtml(saved) : '<span class="text-muted fst-italic small">—</span>');
      const rowObj = allGmRows.find(r => r.id == gmId);
      if (rowObj) rowObj[field] = saved;
      showToast('Sačuvano', 'success');
    } catch (err) {
      \$sp.data('orig', orig)
         .html(orig ? escHtml(orig) : '<span class="text-muted fst-italic small">—</span>');
      showToast('Greška: ' + (err.responseJSON?.error || err.message || err), 'danger');
    }
  }

  \$input.on('blur', save);
  \$input.on('keydown', function(ev) {
    if (ev.key === 'Enter')  { ev.preventDefault(); \$input[0].blur(); }
    if (ev.key === 'Escape') {
      \$input.off('blur');
      const \$sp = \$('<span>').addClass('editable-gm')
        .attr({ 'data-gm-id': gmId, 'data-field': field, 'data-orig': orig })
        .html(orig ? escHtml(orig) : '<span class="text-muted fst-italic small">—</span>');
      \$input.replaceWith(\$sp);
    }
  });
}

// ─── Inline edit – naziv / broj područja ────────────────────────────────────────────────────
function startInlineEditPodrucje(e) {
  const \$span = \$(e.currentTarget);
  const pid   = \$span.data('pid');
  const field = \$span.data('field'); // 'naziv' or 'broj'
  const origText = \$span.text().trim();
  const orig  = origText === '\u2014' ? '' : origText;

  const \$input = \$('<input>').attr('type', 'text').val(orig).addClass('form-control form-control-sm');
  \$span.replaceWith(\$input);
  \$input[0].focus();

  async function save() {
    const val  = \$input.val().trim();
    const \$sp = \$('<span>').addClass('editable-p').attr({ 'data-pid': pid, 'data-field': field });
    \$sp.html(val ? escHtml(val) : '<span class="text-muted">\u2014</span>');
    \$input.replaceWith(\$sp);
    const \$row = \$sp.closest('tr.podrucje-row');
    const nVal  = field === 'naziv' ? val : \$row.find('[data-field="naziv"]').text().replace('\u2014','').trim();
    const bVal  = field === 'broj'  ? val : \$row.find('[data-field="broj"]').text().replace('\u2014','').trim();
    try {
      const json = await \$.ajax({
        url        : '../api.php',
        method     : 'POST',
        contentType: 'application/json',
        dataType   : 'json',
        data       : JSON.stringify({ action: 'update_podrucje', id: parseInt(pid), naziv: nVal || orig, broj_adrese: bVal || null })
      });
      if (!json.success) throw new Error(json.error);
      const saved = field === 'naziv' ? (json.data?.naziv ?? val) : (json.data?.broj_adrese ?? val);
      \$sp.html(saved ? escHtml(saved) : '<span class="text-muted">\u2014</span>');
      showToast('Sačuvano', 'success');
    } catch (err) {
      \$sp.html(orig ? escHtml(orig) : '<span class="text-muted">\u2014</span>');
      showToast('Greška: ' + (err.responseJSON?.error || err.message || err), 'danger');
    }
  }

  \$input.on('blur', save);
  \$input.on('keydown', function(ev) {
    if (ev.key === 'Enter')  { ev.preventDefault(); \$input[0].blur(); }
    if (ev.key === 'Escape') {
      \$input.off('blur');
      const \$sp = \$('<span>').addClass('editable-p').attr({ 'data-pid': pid, 'data-field': field });
      \$sp.html(orig ? escHtml(orig) : '<span class="text-muted">\u2014</span>');
      \$input.replaceWith(\$sp);
    }
  });
}

// ─── Dodavanje GM – inline forma (bez modala) ──────────────────────────────────
function addGmForm() {
  const fid = ++_gmFormCounter;
  const \$card = \$(
    '<div class="card card-body p-2 mb-2 add-gm-form" id="gf-' + fid + '" style="background:rgba(25,135,84,.06);border-color:rgba(25,135,84,.3);">'
    + '<div class="row g-2 align-items-end">'
      + '<div class="col-md-2">'
        + '<label class="form-label small mb-1">Br. GM *</label>'
        + '<input type="text" class="form-control form-control-sm gf-broj" placeholder="npr. 1">'
      + '</div>'
      + '<div class="col-md-4">'
        + '<label class="form-label small mb-1">Naziv</label>'
        + '<input type="text" class="form-control form-control-sm gf-naziv" placeholder="Naziv glasačkog mesta">'
      + '</div>'
      + '<div class="col-md-4">'
        + '<label class="form-label small mb-1">Adresa</label>'
        + '<input type="text" class="form-control form-control-sm gf-adresa" placeholder="Ulica i broj">'
      + '</div>'
      + '<div class="col-md-2 d-flex gap-1">'
        + '<button class="btn btn-sm btn-success gf-save-btn flex-grow-1"><i class="bi bi-save me-1"></i>Sačuvaj</button>'
        + '<button class="btn btn-sm btn-outline-secondary gf-hide-btn" title="Sakrij formu">×</button>'
      + '</div>'
    + '</div>'
    + '</div>'
  );
  \$('#dodaj-gm-forms').append(\$card);
  \$card.find('.gf-broj').focus();
  \$card.find('.gf-hide-btn').on('click', () => \$card.remove());
  \$card.find('.gf-save-btn').on('click', async () => {
    const broj = \$card.find('.gf-broj').val().trim();
    if (!broj) { showToast('Broj GM je obavezan', 'warning'); return; }
    const naziv  = \$card.find('.gf-naziv').val().trim();
    const adresa = \$card.find('.gf-adresa').val().trim();
    const \$btn  = \$card.find('.gf-save-btn').prop('disabled', true);
    try {
      const json = await \$.ajax({
        url        : '../api.php',
        method     : 'POST',
        contentType: 'application/json',
        dataType   : 'json',
        data       : JSON.stringify({ action: 'add_gm', fajl_id: FAJL_ID, broj_gm: broj, naziv, adresa })
      });
      if (!json.success) throw new Error(json.error);
      \$card.remove();
      showToast('Glasačko mesto dodato', 'success');
      loadGmList();
    } catch (e) {
      \$btn.prop('disabled', false);
      showToast('Greška: ' + (e.responseJSON?.error || e.message || e), 'danger');
    }
  });
}

// ─── Dodaje red područja u tabelu (helper) ─────────────────────────────────────
function appendPodrucjeRow(gmId, p) {
  const \$tbody = \$('#ptbody-' + gmId);
  if (\$tbody.length) {
    \$tbody.find('tr td[colspan]').closest('tr').remove();
    \$tbody.append(renderPodrucjeRow(p, gmId));
  } else {
    const \$pd = \$('#pd-' + gmId);
    if (!\$pd.length) return;
    \$pd.find('p.text-muted').remove();
    const tHtml = '<table class="table table-sm table-bordered mb-1" style="font-size:.82rem;">'
      + '<thead class="table-light"><tr><th>Naziv</th><th style="width:70px;">Br.</th>'
      + '<th class="text-center" style="width:50px;">Obriši</th></tr></thead>'
      + '<tbody id="ptbody-' + gmId + '">' + renderPodrucjeRow(p, gmId) + '</tbody></table>';
    \$pd.prepend(tHtml);
  }
}

// ─── JS tokenizer adresa (isti algoritam kao PHP) ──────────────────────────────
function tokenizeAddressStr(input) {
  if (!input || !input.trim()) return [];
  let s = input.replace(/[\\r\\n]+/g, ' ');
  // Spoji word-wrapped crtice: "18-\\n A" → "18-A"
  s = s.replace(/([A-Za-z0-9\\u00C0-\\u017E\\u0400-\\u04FF])-\\s+([A-Za-z0-9\\u00C0-\\u017E\\u0400-\\u04FF])/g, '$1-$2');
  s = s.replace(/\\s{2,}/g, ' ').trim();
  if (!s) return [];

  const rawTokens = s.split(/\\s*,\\s*/);
  const result    = [];
  let street      = '';

  for (let tok of rawTokens) {
    tok = tok.trim();
    if (!tok) continue;
    // Separator "i" / "и"
    if (/^(i|\\u0438)$/i.test(tok)) {
      if (street) { result.push({ naziv: street, broj_adrese: '' }); street = ''; }
      continue;
    }
    // Token počinje cifrom ili je "bb"/"0" → kućni broj za tekuću ulicu
    if (/^\\d/.test(tok) || /^bb$/i.test(tok) || tok === '0') {
      result.push({ naziv: street || '?', broj_adrese: tok });
      continue;
    }
    // Token počinje slovom → nova ulica, opciono sa trailing brojem
    const m = tok.match(/^(.*?)\\s+(\\d[\\w\\-]*)$/);
    if (m && /[A-Za-z\\u0400-\\u04FF\\u00C0-\\u017E]/i.test(m[1])) {
      if (street && !result.some(r => r.naziv === street)) {
        result.push({ naziv: street, broj_adrese: '' });
      }
      street = m[1].trim();
      result.push({ naziv: street, broj_adrese: m[2] });
    } else {
      if (street && !result.some(r => r.naziv === street)) {
        result.push({ naziv: street, broj_adrese: '' });
      }
      street = tok;
    }
  }
  // Ako je ostalo ime ulice bez broja
  if (street && !result.some(r => r.naziv === street)) {
    result.push({ naziv: street, broj_adrese: '' });
  }
  return result;
}

// ─── Sačuvaj lokaciju ────────────────────────────────────────────────────────
// (alias za saveLokaciju, poziva se niže)

// ─── Modal: izmeni lokaciju ───────────────────────────────────────────────────
async function saveLokaciju() {
  const gradId    = \$('#lokGrad').val()    || null;
  const opstinaId = \$('#lokOpstina').val() || null;
  const rutFajlId = \$('#lokRutFajl').val() || null;
  const \$btn = \$('#btnSacuvajLokaciju').prop('disabled', true);
  try {
    const calls = [
      \$.ajax({ url: '../api.php', method: 'POST', contentType: 'application/json', dataType: 'json',
               data: JSON.stringify({ action: 'update_fajl', fajl_id: FAJL_ID, polje: 'grad_id',           vrednost: gradId }) }),
      \$.ajax({ url: '../api.php', method: 'POST', contentType: 'application/json', dataType: 'json',
               data: JSON.stringify({ action: 'update_fajl', fajl_id: FAJL_ID, polje: 'opstina_id',       vrednost: opstinaId }) }),
      \$.ajax({ url: '../api.php', method: 'POST', contentType: 'application/json', dataType: 'json',
               data: JSON.stringify({ action: 'update_fajl', fajl_id: FAJL_ID, polje: 'roditelj_fajl_id', vrednost: rutFajlId }) }),
    ];
    const results = await Promise.all(calls);
    for (const json of results) {
      if (!json.success) throw new Error(json.error);
    }
    bootstrap.Modal.getInstance(document.getElementById('modalLokacija')).hide();
    showToast('Lokacija sačuvana', 'success');
    setTimeout(() => location.reload(), 900);
  } catch (e) {
    showToast('Greška: ' + (e.responseJSON?.error || e.message || e), 'danger');
  } finally {
    \$btn.prop('disabled', false);
  }
}

// ─── Brisanje GM ──────────────────────────────────────────────────────────────
async function deleteGm(gmId, btn) {
  if (!confirm('Obriši ovo glasačko mesto i sve njegove adrese?')) return;
  try {
    const json = await \$.ajax({
      url        : '../api.php',
      method     : 'POST',
      contentType: 'application/json',
      dataType   : 'json',
      data       : JSON.stringify({ action: 'delete_gm', id: gmId })
    });
    if (!json.success) throw new Error(json.error);
    \$(btn).closest('tr.gm-row').remove();
    \$('#pr-' + gmId).remove();
    allGmRows = allGmRows.filter(r => r.id != gmId);
    \$('#gmCount').text(allGmRows.length);
    showToast('Glasačko mesto obrisano', 'success');
  } catch (e) {
    showToast('Greška: ' + (e.responseJSON?.error || e.message || e), 'danger');
  }
}

// ─── Brisanje područja ────────────────────────────────────────────────────────
async function deletePodrucje(pid, btn) {
  if (!confirm('Obriši ovo područje i sve njegove adrese?')) return;
  try {
    const json = await \$.ajax({
      url        : '../api.php',
      method     : 'POST',
      contentType: 'application/json',
      dataType   : 'json',
      data       : JSON.stringify({ action: 'delete_podrucje', id: pid })
    });
    if (!json.success) throw new Error(json.error);
    \$('#prow-' + pid).remove();
    showToast('Područje obrisano', 'success');
  } catch (e) {
    showToast('Greška: ' + (e.responseJSON?.error || e.message || e), 'danger');
  }
}

JS;

layout_end($js);
exit;
