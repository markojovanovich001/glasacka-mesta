<?php
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers.php';
require_once dirname(__DIR__) . '/layout.php';

$lastRunFile = storagePath('logs', 'last_run.txt');
$lastCron    = is_file($lastRunFile) ? trim(file_get_contents($lastRunFile)) : null;

// Tail log fajla (poslednjih 200 redova)
$logFile  = storagePath('logs', 'cron.log');
$logLines = [];
$logSize  = 0;
if (is_file($logFile)) {
    $logSize = filesize($logFile);
    $file    = new SplFileObject($logFile, 'r');
    $file->setFlags(SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
    $all = [];
    foreach ($file as $line) {
        $all[] = rtrim((string)$line);
    }
    $logLines = array_slice($all, -200);
    unset($file, $all);
}

layout_start('Recheck izmena', 'recheck', [['Recheck izmena', null]]);
?>

<!-- ── Recheck terminal ───────────────────────────────────── -->
<div class="table-card mb-4">
  <div class="table-card-header flex-wrap gap-2">
    <i class="bi bi-arrow-clockwise text-secondary"></i>
    <span class="fw-semibold">Recheck terminal</span>
    <span id="scraperStatus"
          class="badge rounded-pill bg-secondary"
          style="font-size:.72rem;">Nije pokrenuto</span>
    <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
      <span id="scraperElapsed"
            style="font-size:.72rem;color:#94a3b8;font-variant-numeric:tabular-nums;min-width:36px;text-align:right;"></span>
      <button id="btnRecheck"
              class="btn btn-sm btn-warning d-flex align-items-center gap-1"
              onclick="startRecheck()">
        <i class="bi bi-arrow-clockwise"></i>
        <span>Recheck izmene</span>
      </button>
      <button id="btnStop"
              class="btn btn-sm btn-danger d-flex align-items-center gap-1"
              onclick="stopRecheck()" disabled>
        <i class="bi bi-stop-fill"></i>
        <span>Zaustavi</span>
      </button>
    </div>
  </div>

  <!-- Progress strip (hidden when idle) -->
  <div id="progressWrap" style="background:#0d1117;padding:8px 16px 10px;display:none;
       border-bottom:1px solid #1e293b;">
    <div class="d-flex justify-content-between align-items-center mb-1">
      <span id="progLabel" style="font-size:.72rem;color:#64748b;">Pokreće se...</span>
      <span id="progPct"   style="font-size:.72rem;color:#64748b;font-variant-numeric:tabular-nums;">0 / —</span>
    </div>
    <div style="height:4px;background:#1e293b;border-radius:3px;overflow:hidden;">
      <div id="progBar" style="width:0%;height:100%;transition:width .35s ease;"
           class="progress-bar bg-warning"></div>
    </div>
    <div class="d-flex gap-4 mt-2">
      <span style="font-size:.71rem;font-family:monospace;color:#fde68a;">
        <i class="bi bi-exclamation-circle me-1"></i>Izmena: <strong id="sNova">0</strong>
      </span>
      <span style="font-size:.71rem;font-family:monospace;color:#64748b;">
        <i class="bi bi-dash-circle me-1"></i>Preskočeno: <strong id="sSkip">0</strong>
      </span>
      <span style="font-size:.71rem;font-family:monospace;color:#fca5a5;">
        <i class="bi bi-x-circle me-1"></i>Greške: <strong id="sErr">0</strong>
      </span>
    </div>
  </div>

  <!-- Terminal output -->
  <div id="terminal"
       style="font-family:ui-monospace,'Cascadia Code',monospace;font-size:.77rem;
              background:#0f172a;min-height:300px;max-height:55vh;
              overflow-y:auto;padding:12px 16px;line-height:1.65;
              color:#e2e8f0;white-space:pre-wrap;word-break:break-all;">
    <?php foreach ($logLines as $line):
        $cls = '';
        if (stripos($line, '[ERROR]') !== false || stripos($line, 'FATALNA') !== false) $cls = 'cron-error';
        elseif (stripos($line, '[WARN]')  !== false) $cls = 'cron-warn';
        elseif (stripos($line, '[DEBUG]') !== false) $cls = 'cron-debug';
        elseif (str_contains($line, '==='))           $cls = 'cron-section';
    ?>
      <span class="<?= $cls ?>"><?= htmlspecialchars($line) . "\n" ?></span>
    <?php endforeach; ?>
  </div>

  <div style="padding:8px 16px;background:#1e293b;border-top:1px solid #0f172a;
              font-size:.74rem;color:#475569;border-radius:0 0 10px 10px;">
    <?php if ($logSize > 0): ?>
      <i class="bi bi-journal-text me-1"></i>
      Prikazano poslednjih 200 redova &middot;
      <?= number_format($logSize / 1024, 1) ?> KB
      <?php if ($lastCron): ?>
        &middot; Poslednji run: <strong style="color:#94a3b8;"><?= htmlspecialchars($lastCron) ?></strong>
      <?php endif; ?>
    <?php else: ?>
      <i class="bi bi-journal-x me-1"></i>Log je prazan &ndash; recheck još nije pokrenut.
    <?php endif; ?>
  </div>
</div>

<!-- ── Info kartica ───────────────────────────────────────── -->
<div class="table-card">
  <div class="table-card-header">
    <i class="bi bi-info-circle text-secondary"></i>
    <span class="fw-semibold">Šta radi Recheck?</span>
  </div>
  <div class="p-3" style="font-size:.875rem;color:#475569;">
    <ul class="mb-0 ps-3" style="line-height:1.8;">
      <li>Preuzima svaki fajl sa RIK sajta i poredi SHA256 hash sa poslednjom sačuvanom verzijom.</li>
      <li>Ako je hash promenjen, <strong>novi fajl se čuva u storage</strong>, a fajl se <strong>flaguje kao "ima izmena"</strong>.</li>
      <li>Ne parsira tabelu glasačkih mesta — to radi First run.</li>
      <li>Stari PDF ostaje sačuvan na disku i dostupan za ručni pregled.</li>
    </ul>
  </div>
</div>

<?php
$js = <<<'JS'
let _sse = null, _running = false, _elapsed = null, _t0 = 0, _total = 0;

function _cls(line) {
  if (/\[ERROR\]|FATALNA/.test(line)) return 'cron-error';
  if (/\[WARN\]/.test(line))          return 'cron-warn';
  if (/\[DEBUG\]/.test(line))         return 'cron-debug';
  if (/⏸|▶|⏹/.test(line))           return 'cron-paused';
  if (/===/.test(line))               return 'cron-section';
  if (/Izmena flagovana|Nova verzija/.test(line)) return 'cron-new';
  if (/Nema izmena/.test(line))       return 'cron-skip';
  return 'cron-info';
}

function _addLine(line) {
  const el = document.getElementById('terminal');
  const sp = document.createElement('span');
  sp.className = _cls(line);
  sp.textContent = line + '\n';
  el.appendChild(sp);
  el.scrollTop = el.scrollHeight;
}

function _setRunning(r) {
  _running = r;
  $('#btnRecheck').prop('disabled', r);
  $('#btnStop').prop('disabled', !r)
               .html('<i class="bi bi-stop-fill"></i> <span>Zaustavi</span>');
  const $st = $('#scraperStatus');
  if (r) {
    $st.attr('class', 'badge rounded-pill bg-warning').text('Radi...');
    $('#progressWrap').show();
  } else {
    $('#progressWrap').hide();
  }
}

function startRecheck() {
  if (_sse) { _sse.close(); _sse = null; }
  _total = 0;
  _t0    = Date.now();
  clearInterval(_elapsed);
  _elapsed = setInterval(() => {
    const s = Math.floor((Date.now() - _t0) / 1000);
    const m = Math.floor(s / 60);
    $('#scraperElapsed').text((m > 0 ? m + 'm ' : '') + (s % 60) + 's');
  }, 1000);

  $('#progBar').css('width', '0%').attr('class', 'progress-bar bg-warning');
  $('#progPct').text('0 / —');
  $('#progLabel').text('Pokreće se...');
  $('#sNova').text('0'); $('#sSkip').text('0'); $('#sErr').text('0');
  _setRunning(true);
  _addLine('--- Recheck pokrenut ' + new Date().toLocaleString('sr-Latn') + ' ---');

  _sse = new EventSource('../api.php?action=run_recheck_stream');

  _sse.onmessage = function(e) {
    let d; try { d = JSON.parse(e.data); } catch { return; }

    if (d.line !== undefined) {
      const totM = d.line.match(/CRON_TOTAL:(\d+)/);
      if (totM) {
        _total = +totM[1];
        $('#progPct').text('0 / ' + _total);
        $('#progLabel').text('Proverava 0 od ' + _total);
        return;
      }
      const stM = d.line.match(/CRON_STATUS (\d+)\/(\d+) Nova:(\d+) Preskoceno:(\d+) Greske:(\d+)/);
      if (stM) {
        const [, done, tot, nova, skip, err] = stM;
        _total = +tot;
        const pct = _total > 0 ? (+done / _total * 100).toFixed(1) : 0;
        $('#progBar').css('width', pct + '%');
        $('#progPct').text(done + ' / ' + tot);
        $('#progLabel').text('Provereno ' + done + ' od ' + tot);
        $('#sNova').text(nova); $('#sSkip').text(skip); $('#sErr').text(err);
        return;
      }
      if (/⏸ Pauzirano/.test(d.line)) {
        $('#scraperStatus').attr('class', 'badge rounded-pill bg-warning').text('Pauzirano');
        $('#progBar').attr('class', 'progress-bar bg-secondary');
      }
      if (/▶ Nastavljam/.test(d.line)) {
        $('#scraperStatus').attr('class', 'badge rounded-pill bg-warning').text('Radi...');
        $('#progBar').attr('class', 'progress-bar bg-warning');
      }
      _addLine(d.line);
    }

    if (d.error) _addLine('[GREŠKA] ' + d.error);
    if (d.done)  _onDone(d.success !== false);
  };

  _sse.onerror = function() {
    if (!_sse || _sse.readyState === EventSource.CONNECTING) return;
    _sse.close(); _sse = null;
    _onDone(false);
  };
}

async function stopRecheck() {
  if (!_running) return;
  $('#btnStop').prop('disabled', true)
              .html('<span class="spinner-border spinner-border-sm me-1"></span>Zaustavljam...');
  try {
    await $.ajax({
      url         : '../api.php',
      method      : 'POST',
      contentType : 'application/json',
      data        : JSON.stringify({ action: 'stop_check' })
    });
  } catch { /* ignoriši */ }
}

function _onDone(ok) {
  clearInterval(_elapsed);
  if (_sse) { _sse.close(); _sse = null; }
  _running = false;
  $('#btnRecheck').prop('disabled', false);
  $('#btnStop').prop('disabled', true)
              .html('<i class="bi bi-stop-fill"></i> <span>Zaustavi</span>');
  $('#scraperStatus')
    .attr('class', 'badge rounded-pill ' + (ok ? 'bg-success' : 'bg-danger'))
    .text(ok ? 'Završeno' : 'Zaustavljeno');
  $('#progBar').attr('class', 'progress-bar ' + (ok ? 'bg-success' : 'bg-danger'));
  if (ok && _total > 0) {
    $('#progBar').css('width', '100%');
    $('#progLabel').text('Završeno – ' + $('#progPct').text());
  }
  _addLine('--- ' + (ok ? 'Završeno' : 'Zaustavljeno') +
           ' ' + new Date().toLocaleString('sr-Latn') + ' ---');
}

$(function() {
  const t = document.getElementById('terminal');
  if (t) t.scrollTop = t.scrollHeight;
});
JS;

layout_end($js);
exit;
