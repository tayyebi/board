<?php
declare(strict_types=1);
session_start();
$TITLE = 'Tayyebi Board';
$LANES_FILE = __DIR__ . '/lanes.csv';
$TASKS_FILE = __DIR__ . '/tasks.csv';
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok(): bool { return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']); }
function b64e(string $s): string { return base64_encode($s); }
function b64d(?string $s): string { if ($s === null || $s === '') return ''; $dec = base64_decode($s, true); return ($dec === false ? $s : $dec); }
function ensure_csv(string $path, array $headers): void { if (!file_exists($path)) { $fh = fopen($path, 'w'); if ($fh) { fputcsv($fh, $headers); fclose($fh); } } }
function load_rows(string $path): array { if (!file_exists($path)) return []; $fh = fopen($path, 'r'); if (!$fh) return []; $rows = []; $headers = fgetcsv($fh); if (!$headers) { fclose($fh); return []; } while (($line = fgetcsv($fh)) !== false) { $row = []; foreach ($headers as $i => $h) { $val = $line[$i] ?? ''; $row[$h] = is_string($val) ? $val : ''; } if (count(array_filter($row, fn($v)=>$v !== '')) === 0) continue; $rows[] = $row; } fclose($fh); return $rows; }
function save_rows(string $path, array $headers, array $rows): bool { $tmp = $path . '.tmp'; $fh = fopen($tmp, 'w'); if (!$fh) return false; fputcsv($fh, $headers); foreach ($rows as $r) { $out = []; foreach ($headers as $h) { $out[] = isset($r[$h]) && is_string($r[$h]) ? $r[$h] : ''; } fputcsv($fh, $out); } fclose($fh); return rename($tmp, $path); }
ensure_csv($LANES_FILE, ['swimlane_b64','column_b64','swimlane_color','column_color','swimlane_order']);
ensure_csv($TASKS_FILE, ['id','title_b64','notes_b64','swimlane_b64','column_b64','due']);
$lanesRows = load_rows($LANES_FILE);
if (empty($lanesRows)) {
    $seed = [];
    foreach ([['Incidents',['Detected','Investigating','Fixing','Verifying','Postmortem']], ['Ops backlog',['Triaged','Planned','In progress','Review','Done']]] as [$swl,$cols]) {
        foreach ($cols as $c) $seed[] = ['swimlane_b64'=>b64e($swl),'column_b64'=>b64e($c),'swimlane_color'=>'','column_color'=>'','swimlane_order'=>'0'];
    }
    save_rows($LANES_FILE, ['swimlane_b64','column_b64','swimlane_color','column_color','swimlane_order'], $seed);
    $lanesRows = $seed;
}
$taskRows = load_rows($TASKS_FILE);
$SWIMLANES = [];
foreach ($lanesRows as $lr) {
    $swl = b64d($lr['swimlane_b64'] ?? '');
    $col = b64d($lr['column_b64'] ?? '');
    $scolor = $lr['swimlane_color'] ?? '';
    $ccolor = $lr['column_color'] ?? '';
    $order = is_numeric($lr['swimlane_order'] ?? '') ? (int)$lr['swimlane_order'] : 0;
    if ($swl === '' || $col === '') continue;
    if (!isset($SWIMLANES[$swl])) $SWIMLANES[$swl] = ['cols'=>[],'color'=>$scolor,'order'=>$order];
    if (!in_array($col, $SWIMLANES[$swl]['cols'], true)) $SWIMLANES[$swl]['cols'][] = $col;
    if ($SWIMLANES[$swl]['color'] === '') $SWIMLANES[$swl]['color'] = $scolor;
}
uasort($SWIMLANES, fn($a,$b)=>($a['order'] <=> $b['order']));
function valid_swimlane(string $s, array $SWIMLANES): bool { return array_key_exists($s, $SWIMLANES); }
function valid_column(string $s, string $swl, array $SWIMLANES): bool { return valid_swimlane($swl,$SWIMLANES) && in_array($s, $SWIMLANES[$swl]['cols'], true); }
function first_swimlane(array $SWIMLANES): string { return array_key_first($SWIMLANES); }
function first_column(string $swl, array $SWIMLANES): string { return $SWIMLANES[$swl]['cols'][0]; }
function next_id(array $taskRows): string { $max=0; foreach ($taskRows as $t){ $max=max($max,(int)($t['id']??0)); } return (string)($max+1); }
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$notice = '';
if ($method === 'POST' && csrf_ok()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_meta') {
        $meta = $_POST['meta'] ?? '';
        $data = json_decode($meta, true);
        if (is_array($data)) {
            $map = $data['swimlanes'] ?? [];
            foreach ($lanesRows as &$lr) {
                $swl = b64d($lr['swimlane_b64'] ?? '');
                $col = b64d($lr['column_b64'] ?? '');
                if (isset($map[$swl])) {
                    $lr['swimlane_color'] = $map[$swl]['color'] ?? ($lr['swimlane_color'] ?? '');
                    $lr['swimlane_order'] = (string)($map[$swl]['order'] ?? ($lr['swimlane_order'] ?? '0'));
                    if (isset($map[$swl]['columns'][$col])) $lr['column_color'] = $map[$swl]['columns'][$col];
                }
            }
            unset($lr);
            save_rows($LANES_FILE, ['swimlane_b64','column_b64','swimlane_color','column_color','swimlane_order'], $lanesRows);
            $notice = 'Settings saved';
            $lanesRows = load_rows($LANES_FILE);
            $SWIMLANES = [];
            foreach ($lanesRows as $lr) {
                $swl = b64d($lr['swimlane_b64'] ?? '');
                $col = b64d($lr['column_b64'] ?? '');
                $scolor = $lr['swimlane_color'] ?? '';
                $order = is_numeric($lr['swimlane_order'] ?? '') ? (int)$lr['swimlane_order'] : 0;
                if ($swl === '' || $col === '') continue;
                if (!isset($SWIMLANES[$swl])) $SWIMLANES[$swl] = ['cols'=>[],'color'=>$scolor,'order'=>$order];
                if (!in_array($col, $SWIMLANES[$swl]['cols'], true)) $SWIMLANES[$swl]['cols'][] = $col;
                if ($SWIMLANES[$swl]['color'] === '') $SWIMLANES[$swl]['color'] = $scolor;
            }
            uasort($SWIMLANES, fn($a,$b)=>($a['order'] <=> $b['order']));
        }
    } elseif ($action === 'add_swimlane') {
        $swl = trim((string)($_POST['swimlane'] ?? ''));
        $firstCol = trim((string)($_POST['first_column'] ?? ''));
        if ($swl !== '' && $firstCol !== '' && !valid_swimlane($swl,$SWIMLANES)) {
            $maxOrder = 0; foreach ($SWIMLANES as $s=>$v) $maxOrder = max($maxOrder, $v['order'] ?? 0);
            $lanesRows[] = ['swimlane_b64'=>b64e($swl),'column_b64'=>b64e($firstCol),'swimlane_color'=>'','column_color'=>'','swimlane_order'=> (string)($maxOrder+1)];
            save_rows($LANES_FILE, ['swimlane_b64','column_b64','swimlane_color','column_color','swimlane_order'], $lanesRows);
            $notice = 'Swimlane added';
        } else { $notice = 'Invalid/duplicate swimlane'; }
    } elseif ($action === 'rename_swimlane') {
        $old = trim((string)($_POST['old_swimlane'] ?? ''));
        $new = trim((string)($_POST['new_swimlane'] ?? ''));
        if ($new !== '' && valid_swimlane($old,$SWIMLANES) && !valid_swimlane($new,$SWIMLANES)) {
            foreach ($lanesRows as &$r) if (b64d($r['swimlane_b64'] ?? '') === $old) $r['swimlane_b64'] = b64e($new);
            foreach ($taskRows as &$t) if (b64d($t['swimlane_b64'] ?? '') === $old) $t['swimlane_b64'] = b64e($new);
            unset($r,$t);
            save_rows($LANES_FILE, ['swimlane_b64','column_b64','swimlane_color','column_color','swimlane_order'], $lanesRows);
            save_rows($TASKS_FILE, ['id','title_b64','notes_b64','swimlane_b64','column_b64','due'], $taskRows);
            $notice = 'Swimlane renamed';
        } else { $notice = 'Invalid rename'; }
    } elseif ($action === 'delete_swimlane') {
        $swl = trim((string)($_POST['swimlane'] ?? ''));
        $fallback = trim((string)($_POST['fallback_swimlane'] ?? ''));
        if (valid_swimlane($swl,$SWIMLANES) && $swl !== $fallback && valid_swimlane($fallback,$SWIMLANES)) {
            $lanesRows = array_values(array_filter($lanesRows, fn($r)=>b64d($r['swimlane_b64'] ?? '') !== $swl));
            $fbCol = first_column($fallback,$SWIMLANES);
            foreach ($taskRows as &$t) if (b64d($t['swimlane_b64'] ?? '') === $swl) { $t['swimlane_b64']=b64e($fallback); $t['column_b64']=b64e($fbCol); }
            unset($t);
            save_rows($LANES_FILE, ['swimlane_b64','column_b64','swimlane_color','column_color','swimlane_order'], $lanesRows);
            save_rows($TASKS_FILE, ['id','title_b64','notes_b64','swimlane_b64','column_b64','due'], $taskRows);
            $notice = 'Swimlane deleted';
        } else { $notice = 'Invalid delete'; }
    } elseif ($action === 'add_column') {
        $swl = trim((string)($_POST['swimlane'] ?? ''));
        $col = trim((string)($_POST['column'] ?? ''));
        if ($col !== '' && valid_swimlane($swl,$SWIMLANES) && !valid_column($col,$swl,$SWIMLANES)) {
            $lanesRows[] = ['swimlane_b64'=>b64e($swl),'column_b64'=>b64e($col),'swimlane_color'=>'','column_color'=>'','swimlane_order'=> (string)($SWIMLANES[$swl]['order'] ?? 0)];
            save_rows($LANES_FILE, ['swimlane_b64','column_b64','swimlane_color','column_color','swimlane_order'], $lanesRows);
            $notice = 'Column added';
        } else { $notice = 'Invalid/duplicate column'; }
    } elseif ($action === 'rename_column') {
        $swl = trim((string)($_POST['swimlane'] ?? ''));
        $old = trim((string)($_POST['old_column'] ?? ''));
        $new = trim((string)($_POST['new_column'] ?? ''));
        if ($new !== '' && valid_swimlane($swl,$SWIMLANES) && valid_column($old,$swl,$SWIMLANES) && !valid_column($new,$swl,$SWIMLANES)) {
            foreach ($lanesRows as &$r) if (b64d($r['swimlane_b64'] ?? '') === $swl && b64d($r['column_b64'] ?? '') === $old) $r['column_b64'] = b64e($new);
            foreach ($taskRows as &$t) if (b64d($t['swimlane_b64'] ?? '') === $swl && b64d($t['column_b64'] ?? '') === $old) $t['column_b64'] = b64e($new);
            unset($r,$t);
            save_rows($LANES_FILE, ['swimlane_b64','column_b64','swimlane_color','column_color','swimlane_order'], $lanesRows);
            save_rows($TASKS_FILE, ['id','title_b64','notes_b64','swimlane_b64','column_b64','due'], $taskRows);
            $notice = 'Column renamed';
        } else { $notice = 'Invalid rename'; }
    } elseif ($action === 'delete_column') {
        $swl = trim((string)($_POST['swimlane'] ?? ''));
        $col = trim((string)($_POST['column'] ?? ''));
        $fallbackCol = trim((string)($_POST['fallback_column'] ?? ''));
        if (valid_column($col,$swl,$SWIMLANES) && $col !== $fallbackCol && valid_column($fallbackCol,$swl,$SWIMLANES)) {
            $lanesRows = array_values(array_filter($lanesRows, fn($r)=> !(b64d($r['swimlane_b64'] ?? '') === $swl && b64d($r['column_b64'] ?? '') === $col)));
            foreach ($taskRows as &$t) if (b64d($t['swimlane_b64'] ?? '') === $swl && b64d($t['column_b64'] ?? '') === $col) $t['column_b64'] = b64e($fallbackCol);
            unset($t);
            save_rows($LANES_FILE, ['swimlane_b64','column_b64','swimlane_color','column_color','swimlane_order'], $lanesRows);
            save_rows($TASKS_FILE, ['id','title_b64','notes_b64','swimlane_b64','column_b64','due'], $taskRows);
            $notice = 'Column deleted';
        } else { $notice = 'Invalid delete'; }
    } elseif ($action === 'create_task') {
        $title = trim((string)($_POST['title'] ?? ''));
        $notes = (string)($_POST['notes'] ?? '');
        $swl   = trim((string)($_POST['swimlane'] ?? ''));
        $col   = trim((string)($_POST['column'] ?? ''));
        $due   = trim((string)($_POST['due'] ?? ''));
        if ($title !== '' && valid_swimlane($swl,$SWIMLANES) && valid_column($col,$swl,$SWIMLANES)) {
            $taskRows[] = ['id'=>next_id($taskRows),'title_b64'=>b64e($title),'notes_b64'=>b64e($notes),'swimlane_b64'=>b64e($swl),'column_b64'=>b64e($col),'due'=>$due];
            save_rows($TASKS_FILE, ['id','title_b64','notes_b64','swimlane_b64','column_b64','due'], $taskRows);
            $notice = 'Task created';
        } else { $notice = 'Title/swimlane/column required'; }
    } elseif ($action === 'edit_task') {
        $id    = (string)($_POST['id'] ?? '');
        $title = trim((string)($_POST['title'] ?? ''));
        $notes = (string)($_POST['notes'] ?? '');
        $swl   = trim((string)($_POST['swimlane'] ?? ''));
        $col   = trim((string)($_POST['column'] ?? ''));
        $due   = trim((string)($_POST['due'] ?? ''));
        $found = false;
        foreach ($taskRows as &$t) {
            if ($t['id'] === $id) {
                if ($title !== '') $t['title_b64'] = b64e($title);
                $t['notes_b64'] = b64e($notes);
                if (valid_swimlane($swl,$SWIMLANES) && valid_column($col,$swl,$SWIMLANES)) {
                    $t['swimlane_b64'] = b64e($swl);
                    $t['column_b64']   = b64e($col);
                }
                $t['due'] = $due;
                $found = true; break;
            }
        }
        unset($t);
        if ($found) { save_rows($TASKS_FILE, ['id','title_b64','notes_b64','swimlane_b64','column_b64','due'], $taskRows); $notice = 'Task updated'; } else { $notice = 'Task not found'; }
    } elseif ($action === 'delete_task') {
        $id = (string)($_POST['id'] ?? '');
        $taskRows = array_values(array_filter($taskRows, fn($t)=>($t['id'] ?? '') !== $id));
        save_rows($TASKS_FILE, ['id','title_b64','notes_b64','swimlane_b64','column_b64','due'], $taskRows);
        $notice = 'Task deleted';
    } elseif ($action === 'move_task') {
        $id = (string)($_POST['id'] ?? '');
        $swl = trim((string)($_POST['swimlane'] ?? ''));
        $col = trim((string)($_POST['column'] ?? ''));
        if (valid_swimlane($swl,$SWIMLANES) && valid_column($col,$swl,$SWIMLANES)) {
            $updated = false;
            foreach ($taskRows as &$t) {
                if (($t['id'] ?? '') === $id) {
                    $t['swimlane_b64'] = b64e($swl);
                    $t['column_b64']   = b64e($col);
                    $updated = true; break;
                }
            }
            unset($t);
            if ($updated) { save_rows($TASKS_FILE, ['id','title_b64','notes_b64','swimlane_b64','column_b64','due'], $taskRows); $notice = 'Task moved'; } else { $notice = 'Task not found'; }
        } else { $notice = 'Invalid target'; }
    }
}
$group = [];
foreach ($SWIMLANES as $swl => $meta) { $group[$swl] = []; foreach ($meta['cols'] as $c) $group[$swl][$c] = []; }
foreach ($taskRows as $t) {
    $swl = b64d($t['swimlane_b64'] ?? ''); if (!valid_swimlane($swl,$SWIMLANES)) $swl = first_swimlane($SWIMLANES);
    $col = b64d($t['column_b64'] ?? ''); if (!valid_column($col,$swl,$SWIMLANES)) $col = first_column($swl,$SWIMLANES);
    $group[$swl][$col][] = $t;
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($TITLE); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--bg:#f7f7f7;--panel:#fff;--text:#222;--muted:#666;--shadow:rgba(0,0,0,0.06);--modal-bg:rgba(0,0,0,0.45)}
*{box-sizing:border-box}
body{margin:0;padding:10px;background:var(--bg);color:var(--text);font:14px/1.4 system-ui,-apple-system,"Segoe UI",Roboto,Arial}
header{display:flex;align-items:center;justify-content:space-between;margin:6px 4px 12px;gap:12px}
h1{font-size:16px;margin:0}
.meta{font-size:12px;color:var(--muted);display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.btn{font-size:12px;padding:6px 8px;border:1px solid #ddd;border-radius:6px;background:#fafafa;cursor:pointer}
.panel{margin-bottom:12px;padding:8px;background:#fff;border:1px solid #e5e5e5;border-radius:8px;box-shadow:0 1px 2px var(--shadow)}
.form{display:flex;gap:6px;flex-wrap:wrap;padding:8px;border:1px dashed #ddd;border-radius:6px;background:#fcfcfc}
input,select,textarea{font:inherit;padding:6px;border:1px solid #ddd;border-radius:4px}
textarea{min-width:220px;min-height:60px}
.swimlane{margin-bottom:12px;padding:8px;background:#fff;border:1px solid #e5e5e5;border-radius:8px;box-shadow:0 1px 2px var(--shadow)}
.swl-head{display:flex;align-items:center;gap:8px;border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:8px;font-weight:700}
.stripe{width:12px;height:18px;flex:0 0 12px;border-radius:3px;background:repeating-linear-gradient(45deg,rgba(0,0,0,0.08) 0px,rgba(0,0,0,0.08) 4px,transparent 4px,transparent 8px)}
.cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px}
.col{background:var(--panel);border:1px solid #e5e5e5;border-radius:6px;overflow:hidden;box-shadow:0 1px 2px var(--shadow)}
.col-head{padding:8px 10px;font-weight:600;display:flex;align-items:center;gap:8px;border-bottom:1px solid #eee}
.col-body{padding:8px;min-height:60px}
.card{background:#fff;border:1px solid #eee;border-radius:6px;padding:8px;margin:6px 0;box-shadow:0 1px 1px var(--shadow);display:flex;flex-direction:column;gap:6px}
.title{font-weight:600;margin-bottom:4px}
.info{font-size:12px;color:var(--muted);display:flex;gap:8px;flex-wrap:wrap}
.actions{margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.empty{color:#999;font-size:12px;padding:6px}
.modal-backdrop{position:fixed;inset:0;background:var(--modal-bg);display:none;align-items:center;justify-content:center;z-index:9999}
.modal{background:#fff;border-radius:8px;width:90%;max-width:900px;max-height:90vh;overflow:auto;padding:12px;box-shadow:0 8px 30px rgba(0,0,0,0.3)}
.modal .modal-head{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px}
.modal .close{cursor:pointer;border:0;background:transparent;font-size:18px}
.lane-row{display:flex;align-items:center;gap:8px;padding:6px;border:1px solid #eee;border-radius:6px;background:#fafafa}
.lane-handle{cursor:grab;padding:6px;border:1px solid #ddd;border-radius:6px;background:#fff}
.col-chip{padding:4px 8px;background:#fff;border:1px solid #ddd;border-radius:6px;display:inline-flex;gap:6px;align-items:center}
.color-input{width:36px;height:28px;border:1px solid #ddd;padding:0}
@media(max-width:600px){.cols{grid-template-columns:1fr}.modal{width:96%}}
</style>
</head>
<body>
<header>
  <div style="display:flex;align-items:center;gap:12px">
    <h1><?php echo htmlspecialchars($TITLE); ?></h1>
    <div class="meta">
      <span><?php echo count($taskRows); ?> items</span>
      <span>Tasks: <?php echo htmlspecialchars(file_exists($TASKS_FILE)?date('Y-m-d H:i', filemtime($TASKS_FILE)):'—'); ?></span>
      <span>Lanes: <?php echo htmlspecialchars(file_exists($LANES_FILE)?date('Y-m-d H:i', filemtime($LANES_FILE)):'—'); ?></span>
    </div>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <button class="btn" onclick="openSettings()">Settings</button>
    <button class="btn" onclick="location.reload()">Refresh</button>
  </div>
</header>

<div class="panel">
  <div class="label">Create task</div>
  <form class="form" method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
    <input type="hidden" name="action" value="create_task">
    <input type="text" name="title" placeholder="Title" required>
    <select name="swimlane" required onchange="syncColumns(this)">
      <?php foreach ($SWIMLANES as $swl => $meta): ?><option value="<?php echo htmlspecialchars($swl); ?>"><?php echo htmlspecialchars($swl); ?></option><?php endforeach; ?>
    </select>
    <select name="column" required></select>
    <input type="text" name="due" placeholder="Due (optional)">
    <textarea name="notes" placeholder="Notes (optional)"></textarea>
    <button class="btn" type="submit">Add</button>
  </form>
</div>

<?php foreach ($group as $swl => $cols): ?>
<section class="swimlane" data-swl="<?php echo htmlspecialchars($swl); ?>">
  <div class="swl-head">
    <div class="stripe" aria-hidden="true" style="<?php echo ($SWIMLANES[$swl]['color'] ? "background:linear-gradient(45deg, {$SWIMLANES[$swl]['color']}, rgba(0,0,0,0.06));" : ''); ?>"></div>
    <div><?php echo htmlspecialchars($swl); ?></div>
  </div>
  <div class="cols">
    <?php foreach ($cols as $col => $tasks): ?>
    <div class="col" data-col="<?php echo htmlspecialchars($col); ?>">
      <div class="col-head">
        <div class="col-stripe" aria-hidden="true" style="<?php
          $colColor = '';
          foreach ($lanesRows as $lr) { if (b64d($lr['swimlane_b64'] ?? '') === $swl && b64d($lr['column_b64'] ?? '') === $col) { $colColor = $lr['column_color'] ?? ''; break; } }
          echo ($colColor ? "background:linear-gradient(45deg, {$colColor}, rgba(0,0,0,0.06));" : '');
        ?>"></div>
        <div><?php echo htmlspecialchars($col); ?></div>
      </div>
      <div class="col-body" ondragover="event.preventDefault()" ondrop="dropOn(event,'<?php echo htmlspecialchars($swl); ?>','<?php echo htmlspecialchars($col); ?>')">
        <?php if (empty($tasks)): ?><div class="empty">No items</div><?php else: foreach ($tasks as $t): $title=b64d($t['title_b64'] ?? ''); $notes=b64d($t['notes_b64'] ?? ''); $tswl=b64d($t['swimlane_b64'] ?? ''); $tcol=b64d($t['column_b64'] ?? ''); $tid=htmlspecialchars($t['id'] ?? ''); ?>
        <div class="card" draggable="true" ondragstart="dragStart(event,'<?php echo $tid; ?>')">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <div class="title"><?php echo htmlspecialchars($title ?: '(Untitled)'); ?></div>
            <div style="display:flex;gap:6px;align-items:center">
              <button class="btn" onclick="openTaskSettings('<?php echo $tid; ?>','<?php echo rawurlencode($title); ?>','<?php echo rawurlencode($notes); ?>','<?php echo rawurlencode($tswl); ?>','<?php echo rawurlencode($tcol); ?>','<?php echo rawurlencode($t['due'] ?? ''); ?>');return false;">Task settings</button>
            </div>
          </div>
          <div class="info">
            <span>ID: <?php echo $tid; ?></span>
            <span>Swimlane: <?php echo htmlspecialchars($tswl); ?></span>
            <span>Column: <?php echo htmlspecialchars($tcol); ?></span>
            <?php if (!empty($t['due'])): ?><span>Due: <?php echo htmlspecialchars($t['due']); ?></span><?php endif; ?>
          </div>
          <?php if ($notes !== ''): ?><div class="info" style="margin-top:6px"><?php echo nl2br(htmlspecialchars($notes)); ?></div><?php endif; ?>
          <div class="actions">
            <details><summary class="btn">Move</summary>
              <form method="post" style="margin-top:6px">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
                <input type="hidden" name="action" value="move_task">
                <input type="hidden" name="id" value="<?php echo $tid; ?>">
                <select name="swimlane" onchange="syncColumns(this)">
                  <?php foreach ($SWIMLANES as $swName => $swMeta): ?><option value="<?php echo htmlspecialchars($swName); ?>" <?php echo ($swName===$tswl?'selected':''); ?>><?php echo htmlspecialchars($swName); ?></option><?php endforeach; ?>
                </select>
                <select name="column" data-current="<?php echo htmlspecialchars($tcol); ?>"></select>
                <button class="btn" type="submit">Move</button>
              </form>
            </details>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this task?')">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
              <input type="hidden" name="action" value="delete_task">
              <input type="hidden" name="id" value="<?php echo $tid; ?>">
              <button class="btn" type="submit">Delete</button>
            </form>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>

<div id="settingsModal" class="modal-backdrop" onclick="if(event.target===this) closeSettings()">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="settingsTitle">
    <div class="modal-head">
      <h2 id="settingsTitle">Board Settings</h2>
      <button class="close" onclick="closeSettings()">✕</button>
    </div>
    <div class="modal-body">
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <div style="flex:1 1 420px;min-width:280px">
          <h3 style="margin:6px 0 8px">Reorder Swimlanes & Colors</h3>
          <div id="lanesList" style="display:flex;flex-direction:column;gap:8px">
            <?php $idx=0; foreach ($SWIMLANES as $swl => $meta): $idx++; ?>
            <div class="lane-row" data-swl="<?php echo htmlspecialchars($swl); ?>" draggable="true" ondragstart="dragLaneStart(event)" ondragover="event.preventDefault()" ondrop="dropLane(event)">
              <div class="lane-handle">☰</div>
              <div style="flex:1">
                <strong><?php echo htmlspecialchars($swl); ?></strong>
                <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
                  <?php foreach ($meta['cols'] as $c): $colColor=''; foreach ($lanesRows as $lr) { if (b64d($lr['swimlane_b64'] ?? '') === $swl && b64d($lr['column_b64'] ?? '') === $c) { $colColor = $lr['column_color'] ?? ''; break; } } ?>
                  <span class="col-chip"><?php echo htmlspecialchars($c); ?> <input class="color-input" type="color" data-swl="<?php echo htmlspecialchars($swl); ?>" data-col="<?php echo htmlspecialchars($c); ?>" value="<?php echo htmlspecialchars($colColor ?: '#ffffff'); ?>"></span>
                  <?php endforeach; ?>
                </div>
              </div>
              <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end">
                <input class="color-input" type="color" data-swl="<?php echo htmlspecialchars($swl); ?>" value="<?php echo htmlspecialchars($meta['color'] ?: '#ffffff'); ?>">
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div style="flex:1 1 320px;min-width:260px">
          <h3 style="margin:6px 0 8px">Manage Swimlanes</h3>
          <form class="form" method="post" style="margin-bottom:8px">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
            <input type="hidden" name="action" value="add_swimlane">
            <input type="text" name="swimlane" placeholder="New swimlane" required>
            <input type="text" name="first_column" placeholder="First column" required>
            <button class="btn" type="submit">Add</button>
          </form>

          <form class="form" method="post" style="margin-bottom:8px">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
            <input type="hidden" name="action" value="rename_swimlane">
            <select name="old_swimlane" required><?php foreach ($SWIMLANES as $swl=>$m): ?><option value="<?php echo htmlspecialchars($swl); ?>"><?php echo htmlspecialchars($swl); ?></option><?php endforeach; ?></select>
            <input type="text" name="new_swimlane" placeholder="New name" required>
            <button class="btn" type="submit">Rename</button>
          </form>

          <form class="form" method="post" onsubmit="return confirm('Delete swimlane and reassign tasks to fallback?')" style="margin-bottom:8px">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
            <input type="hidden" name="action" value="delete_swimlane">
            <select name="swimlane" required><?php foreach ($SWIMLANES as $swl=>$m): ?><option value="<?php echo htmlspecialchars($swl); ?>"><?php echo htmlspecialchars($swl); ?></option><?php endforeach; ?></select>
            <select name="fallback_swimlane" required><?php foreach ($SWIMLANES as $swl=>$m): ?><option value="<?php echo htmlspecialchars($swl); ?>"><?php echo htmlspecialchars($swl); ?></option><?php endforeach; ?></select>
            <button class="btn" type="submit">Delete</button>
          </form>

          <hr style="margin:12px 0;border:none;border-top:1px solid #eee">

          <h3 style="margin:6px 0 8px">Manage Columns</h3>
          <form class="form" method="post" style="margin-bottom:8px">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
            <input type="hidden" name="action" value="add_column">
            <select name="swimlane" required><?php foreach ($SWIMLANES as $swl=>$m): ?><option value="<?php echo htmlspecialchars($swl); ?>"><?php echo htmlspecialchars($swl); ?></option><?php endforeach; ?></select>
            <input type="text" name="column" placeholder="New column" required>
            <button class="btn" type="submit">Add</button>
          </form>

          <form class="form" method="post" style="margin-bottom:8px">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
            <input type="hidden" name="action" value="rename_column">
            <select name="swimlane" required><?php foreach ($SWIMLANES as $swl=>$m): ?><option value="<?php echo htmlspecialchars($swl); ?>"><?php echo htmlspecialchars($swl); ?></option><?php endforeach; ?></select>
            <input type="text" name="old_column" placeholder="Old column" required>
            <input type="text" name="new_column" placeholder="New column" required>
            <button class="btn" type="submit">Rename</button>
          </form>

          <form class="form" method="post" onsubmit="return confirm('Delete column and reassign tasks to fallback column?')">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
            <input type="hidden" name="action" value="delete_column">
            <select name="swimlane" required><?php foreach ($SWIMLANES as $swl=>$m): ?><option value="<?php echo htmlspecialchars($swl); ?>"><?php echo htmlspecialchars($swl); ?></option><?php endforeach; ?></select>
            <input type="text" name="column" placeholder="Column to delete" required>
            <input type="text" name="fallback_column" placeholder="Fallback column" required>
            <button class="btn" type="submit">Delete</button>
          </form>

          <div style="margin-top:12px;display:flex;gap:8px">
            <button class="btn" onclick="saveSettings()">Save colors & order</button>
            <button class="btn" onclick="closeSettings()">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="taskModal" class="modal-backdrop" onclick="if(event.target===this) closeTaskSettings()">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="taskTitle">
    <div class="modal-head">
      <h2 id="taskTitle">Task Settings</h2>
      <button class="close" onclick="closeTaskSettings()">✕</button>
    </div>
    <div class="modal-body">
      <form id="taskSettingsForm" class="form" method="post">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="edit_task">
        <input type="hidden" name="id" id="task_id" value="">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <input type="text" name="title" id="task_title" placeholder="Title" required style="flex:1 1 300px">
          <input type="text" name="due" id="task_due" placeholder="Due (optional)">
        </div>
        <div style="display:flex;gap:8px;margin-top:8px">
          <select name="swimlane" id="task_swimlane" onchange="syncColumns(this)" required><?php foreach ($SWIMLANES as $swl=>$m): ?><option value="<?php echo htmlspecialchars($swl); ?>"><?php echo htmlspecialchars($swl); ?></option><?php endforeach; ?></select>
          <select name="column" id="task_column" required></select>
        </div>
        <textarea name="notes" id="task_notes" placeholder="Notes" style="width:100%;margin-top:8px"></textarea>
        <div style="display:flex;gap:8px;margin-top:8px">
          <button class="btn" type="submit">Save</button>
          <button class="btn" type="button" onclick="deleteTaskFromModal()">Delete</button>
          <button class="btn" type="button" onclick="closeTaskSettings()">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let dragId = null;
function dragStart(ev,id){dragId=id}
function dropOn(ev,swl,col){if(!dragId) return; const fd=new FormData(); fd.append('csrf','<?php echo htmlspecialchars($_SESSION['csrf']); ?>'); fd.append('action','move_task'); fd.append('id',dragId); fd.append('swimlane',swl); fd.append('column',col); fetch(location.href,{method:'POST',body:fd}).then(()=>location.reload()); dragId=null}
const SWL = <?php echo json_encode(array_map(fn($m)=>$m['cols'],$SWIMLANES), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
function syncColumns(sel){ const form=sel.closest('form'); const colSel=form.querySelector('select[name="column"]'); if(!colSel) return; const swl=sel.value; const cols=SWL[swl]||[]; colSel.innerHTML=''; cols.forEach(c=>{const opt=document.createElement('option'); opt.value=c; opt.textContent=c; colSel.appendChild(opt)}); const current=colSel.getAttribute('data-current'); if(current){ for(const o of colSel.options) if(o.value===current){o.selected=true;break} colSel.removeAttribute('data-current') } }
document.addEventListener('DOMContentLoaded',()=>{ document.querySelectorAll('form').forEach(f=>{ const swSel=f.querySelector('select[name="swimlane"]'); const colSel=f.querySelector('select[name="column"]'); if(swSel && colSel) syncColumns(swSel) }) });

function openSettings(){document.getElementById('settingsModal').style.display='flex'}
function closeSettings(){document.getElementById('settingsModal').style.display='none'}

let laneDragEl=null;
function dragLaneStart(ev){ laneDragEl=ev.currentTarget; ev.dataTransfer?.setData('text/plain','drag') }
function dropLane(ev){ ev.preventDefault(); if(!laneDragEl) return; const target=ev.currentTarget; const list=target.parentElement; if(!list) return; list.insertBefore(laneDragEl, target.nextSibling); laneDragEl=null }

function saveSettings(){
  const rows = document.querySelectorAll('#lanesList .lane-row');
  const meta = { swimlanes: {} };
  let order = 0;
  rows.forEach(r=>{
    const swl = r.getAttribute('data-swl');
    order++;
    const swColorInput = r.querySelector('input.color-input[data-swl="'+swl+'"]');
    const swColor = swColorInput ? swColorInput.value : '';
    const colInputs = r.querySelectorAll('input.color-input[data-col]');
    const cols = {};
    colInputs.forEach(ci=>{ const col = ci.getAttribute('data-col'); cols[col] = ci.value });
    meta.swimlanes[swl] = { color: swColor, order: order, columns: cols };
  });
  const fd = new FormData();
  fd.append('csrf','<?php echo htmlspecialchars($_SESSION['csrf']); ?>');
  fd.append('action','save_meta');
  fd.append('meta', JSON.stringify(meta));
  fetch(location.href, { method:'POST', body:fd }).then(()=>location.reload());
}

function prefillRenameSwimlane(swl){ const sel=document.querySelector('select[name="old_swimlane"]'); if(sel) sel.value=swl; const input=document.querySelector('input[name="new_swimlane"]'); if(input){ input.value=''; input.focus() } }
function prefillDeleteSwimlane(swl){ const sel=document.querySelector('form[action] select[name="swimlane"]'); if(sel) sel.value=swl }
function prefillRenameColumn(swl,col){ const sel=document.querySelector('#renameColumnForm select[name="swimlane"]'); if(sel) sel.value=swl; const old=document.querySelector('#renameColumnForm input[name="old_column"]'); if(old) old.value=col; const neu=document.querySelector('#renameColumnForm input[name="new_column"]'); if(neu) neu.focus() }
function prefillDeleteColumn(swl,col){ const sel=document.querySelector('#deleteColumnForm select[name="swimlane"]'); if(sel) sel.value=swl; const c=document.querySelector('#deleteColumnForm input[name="column"]'); if(c) c.value=col }

function openTaskSettings(id,titleEnc,notesEnc,swlEnc,colEnc,dueEnc){
  const title = decodeURIComponent(titleEnc);
  const notes = decodeURIComponent(notesEnc);
  const swl = decodeURIComponent(swlEnc);
  const col = decodeURIComponent(colEnc);
  const due = decodeURIComponent(dueEnc);
  document.getElementById('task_id').value = id;
  document.getElementById('task_title').value = title;
  document.getElementById('task_notes').value = notes;
  document.getElementById('task_due').value = due;
  const swSel = document.getElementById('task_swimlane');
  if(swSel) swSel.value = swl;
  syncColumns(swSel);
  const colSel = document.getElementById('task_column');
  if(colSel){ colSel.setAttribute('data-current', col); syncColumns(swSel) }
  document.getElementById('taskModal').style.display = 'flex';
  setTimeout(()=>document.getElementById('task_title').focus(),50);
}
function closeTaskSettings(){ document.getElementById('taskModal').style.display='none' }
function deleteTaskFromModal(){ if(!confirm('Delete this task?')) return; const id=document.getElementById('task_id').value; const fd=new FormData(); fd.append('csrf','<?php echo htmlspecialchars($_SESSION['csrf']); ?>'); fd.append('action','delete_task'); fd.append('id',id); fetch(location.href,{method:'POST',body:fd}).then(()=>location.reload()) }
</script>
</body>
</html>
