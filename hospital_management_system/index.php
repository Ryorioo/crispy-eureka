<?php
// ============================================================
//  Hospital Management — Visual Database Manager
//  Place this folder in: C:/xampp/htdocs/db_manager/
//  Access via: http://localhost/db_manager/
// ============================================================

session_start();

// ---------- DB CONFIG ----------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hospital_management');

// ---------- CONNECT ----------
function getConn($db = true) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, $db ? DB_NAME : null);
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// ---------- AJAX HANDLER ----------
if (isset($_POST['action']) || isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'];
    $conn   = getConn();

    switch ($action) {

        // ---- List all tables ----
        case 'get_tables':
            $res = $conn->query("SHOW TABLES");
            $tables = [];
            while ($row = $res->fetch_row()) $tables[] = $row[0];
            echo json_encode(['tables' => $tables]);
            break;

        // ---- Full structure of one table ----
        case 'get_structure':
            $table = $conn->real_escape_string($_POST['table']);
            $cols  = [];
            $res   = $conn->query("SHOW FULL COLUMNS FROM `$table`");
            while ($r = $res->fetch_assoc()) $cols[] = $r;

            // Foreign keys
            $fks = [];
            $r2  = $conn->query("
                SELECT kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME,
                       rc.DELETE_RULE, rc.UPDATE_RULE, kcu.CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE kcu
                JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                  ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                 AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
                WHERE kcu.TABLE_SCHEMA = '".DB_NAME."'
                  AND kcu.TABLE_NAME   = '$table'
                  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ");
            while ($r = $r2->fetch_assoc()) $fks[$r['COLUMN_NAME']] = $r;

            // Indexes
            $idxs = [];
            $r3   = $conn->query("SHOW INDEX FROM `$table`");
            while ($r = $r3->fetch_assoc()) $idxs[] = $r;

            echo json_encode(['columns' => $cols, 'foreign_keys' => $fks, 'indexes' => $idxs]);
            break;

        // ---- Row count ----
        case 'get_row_count':
            $table = $conn->real_escape_string($_POST['table']);
            $res   = $conn->query("SELECT COUNT(*) as cnt FROM `$table`");
            $row   = $res->fetch_assoc();
            echo json_encode(['count' => $row['cnt']]);
            break;

        // ---- Add column ----
        case 'add_column':
            $table   = $conn->real_escape_string($_POST['table']);
            $name    = $conn->real_escape_string($_POST['col_name']);
            $type    = $_POST['col_type'];
            $nn      = isset($_POST['not_null'])   && $_POST['not_null']   === '1' ? 'NOT NULL' : 'NULL';
            $unique  = isset($_POST['unique_col']) && $_POST['unique_col'] === '1';
            $ai      = isset($_POST['auto_inc'])   && $_POST['auto_inc']   === '1' ? 'AUTO_INCREMENT' : '';
            $default = trim($_POST['default_val'] ?? '');
            $defSQL  = $default !== '' ? "DEFAULT '$default'" : '';
            $sql     = "ALTER TABLE `$table` ADD COLUMN `$name` $type $nn $defSQL $ai";
            if ($unique) $sql .= ", ADD UNIQUE (`$name`)";
            if ($conn->query($sql)) echo json_encode(['success' => true, 'sql' => $sql]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Edit column (CHANGE) ----
        case 'edit_column':
            $table   = $conn->real_escape_string($_POST['table']);
            $old     = $conn->real_escape_string($_POST['old_name']);
            $new     = $conn->real_escape_string($_POST['col_name']);
            $type    = $_POST['col_type'];
            $nn      = isset($_POST['not_null'])   && $_POST['not_null']   === '1' ? 'NOT NULL' : 'NULL';
            $default = trim($_POST['default_val'] ?? '');
            $defSQL  = $default !== '' ? "DEFAULT '$default'" : 'DEFAULT NULL';
            $sql     = "ALTER TABLE `$table` CHANGE `$old` `$new` $type $nn $defSQL";
            if ($conn->query($sql)) echo json_encode(['success' => true, 'sql' => $sql]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Drop column ----
        case 'drop_column':
            $table = $conn->real_escape_string($_POST['table']);
            $col   = $conn->real_escape_string($_POST['col_name']);
            $sql   = "ALTER TABLE `$table` DROP COLUMN `$col`";
            if ($conn->query($sql)) echo json_encode(['success' => true]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Add foreign key ----
        case 'add_fk':
            $table    = $conn->real_escape_string($_POST['table']);
            $col      = $conn->real_escape_string($_POST['col']);
            $ref_t    = $conn->real_escape_string($_POST['ref_table']);
            $ref_c    = $conn->real_escape_string($_POST['ref_col']);
            $onDel    = $_POST['on_delete'];
            $onUpd    = $_POST['on_update'];
            $fkName   = 'fk_'.$table.'_'.$col.'_'.time();
            $sql      = "ALTER TABLE `$table` ADD CONSTRAINT `$fkName`
                         FOREIGN KEY (`$col`) REFERENCES `$ref_t`(`$ref_c`)
                         ON DELETE $onDel ON UPDATE $onUpd";
            if ($conn->query($sql)) echo json_encode(['success' => true, 'sql' => $sql]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Drop foreign key ----
        case 'drop_fk':
            $table  = $conn->real_escape_string($_POST['table']);
            $fkName = $conn->real_escape_string($_POST['fk_name']);
            $sql    = "ALTER TABLE `$table` DROP FOREIGN KEY `$fkName`";
            if ($conn->query($sql)) echo json_encode(['success' => true]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Add index ----
        case 'add_index':
            $table   = $conn->real_escape_string($_POST['table']);
            $col     = $conn->real_escape_string($_POST['col']);
            $idxType = $_POST['idx_type']; // INDEX | UNIQUE | FULLTEXT
            $idxName = 'idx_'.$col.'_'.time();
            $sql     = "ALTER TABLE `$table` ADD $idxType `$idxName` (`$col`)";
            if ($conn->query($sql)) echo json_encode(['success' => true, 'sql' => $sql]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Drop index ----
        case 'drop_index':
            $table   = $conn->real_escape_string($_POST['table']);
            $idxName = $conn->real_escape_string($_POST['idx_name']);
            $sql     = "ALTER TABLE `$table` DROP INDEX `$idxName`";
            if ($conn->query($sql)) echo json_encode(['success' => true]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Create table ----
        case 'create_table':
            $name  = $conn->real_escape_string($_POST['table_name']);
            $pk    = $conn->real_escape_string($_POST['pk_name']);
            $engine = $_POST['engine'] ?? 'InnoDB';
            $sql   = "CREATE TABLE IF NOT EXISTS `$name` (
                        `$pk` INT NOT NULL AUTO_INCREMENT,
                        PRIMARY KEY (`$pk`)
                      ) ENGINE=$engine DEFAULT CHARSET=utf8mb4";
            if ($conn->query($sql)) echo json_encode(['success' => true]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Drop table ----
        case 'drop_table':
            $table = $conn->real_escape_string($_POST['table']);
            // Disable FK checks temporarily
            $conn->query("SET FOREIGN_KEY_CHECKS=0");
            $sql = "DROP TABLE IF EXISTS `$table`";
            $ok  = $conn->query($sql);
            $conn->query("SET FOREIGN_KEY_CHECKS=1");
            if ($ok) echo json_encode(['success' => true]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Truncate table ----
        case 'truncate_table':
            $table = $conn->real_escape_string($_POST['table']);
            $conn->query("SET FOREIGN_KEY_CHECKS=0");
            $sql = "TRUNCATE TABLE `$table`";
            $ok  = $conn->query($sql);
            $conn->query("SET FOREIGN_KEY_CHECKS=1");
            if ($ok) echo json_encode(['success' => true]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Rename table ----
        case 'rename_table':
            $old = $conn->real_escape_string($_POST['old_name']);
            $new = $conn->real_escape_string($_POST['new_name']);
            $sql = "RENAME TABLE `$old` TO `$new`";
            if ($conn->query($sql)) echo json_encode(['success' => true]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Get table columns (for FK ref picker) ----
        case 'get_columns':
            $table = $conn->real_escape_string($_POST['table']);
            $res   = $conn->query("SHOW COLUMNS FROM `$table`");
            $cols  = [];
            while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
            echo json_encode(['columns' => $cols]);
            break;

        // ---- Run raw SQL ----
        case 'run_sql':
            $sql    = trim($_POST['sql']);
            $result = $conn->query($sql);
            if ($result === true) {
                echo json_encode(['success' => true, 'affected' => $conn->affected_rows, 'sql' => $sql]);
            } elseif ($result === false) {
                echo json_encode(['error' => $conn->error]);
            } else {
                $rows = [];
                $fields = [];
                while ($f = $result->fetch_field()) $fields[] = $f->name;
                while ($r = $result->fetch_assoc()) $rows[] = $r;
                echo json_encode(['rows' => $rows, 'fields' => $fields]);
            }
            break;

        // ---- Browse rows (paginated + search) ----
        case 'browse_data':
            $table  = $conn->real_escape_string($_POST['table']);
            $page   = max(1, intval($_POST['page'] ?? 1));
            $limit  = intval($_POST['limit'] ?? 25);
            $search = trim($_POST['search'] ?? '');
            $offset = ($page - 1) * $limit;

            // Get column names
            $colRes = $conn->query("SHOW COLUMNS FROM `$table`");
            $cols   = [];
            while ($r = $colRes->fetch_assoc()) $cols[] = $r['Field'];

            // Build WHERE for search
            $where = '';
            if ($search !== '') {
                $s      = $conn->real_escape_string($search);
                $parts  = array_map(fn($c) => "CAST(`$c` AS CHAR) LIKE '%$s%'", $cols);
                $where  = 'WHERE ' . implode(' OR ', $parts);
            }

            $total = $conn->query("SELECT COUNT(*) as c FROM `$table` $where")->fetch_assoc()['c'];
            $rows  = [];
            $res   = $conn->query("SELECT * FROM `$table` $where LIMIT $limit OFFSET $offset");
            while ($r = $res->fetch_assoc()) $rows[] = $r;

            echo json_encode([
                'rows'    => $rows,
                'columns' => $cols,
                'total'   => intval($total),
                'page'    => $page,
                'limit'   => $limit,
                'pages'   => max(1, ceil($total / $limit)),
            ]);
            break;

        // ---- Insert row ----
        case 'insert_row':
            $table  = $conn->real_escape_string($_POST['table']);
            $data_r = json_decode($_POST['row_data'], true);
            $cols2  = [];
            $vals   = [];
            foreach ($data_r as $col => $val) {
                $cols2[] = '`' . $conn->real_escape_string($col) . '`';
                $vals[]  = $val === '' ? 'NULL' : "'" . $conn->real_escape_string($val) . "'";
            }
            $sql = "INSERT INTO `$table` (" . implode(',', $cols2) . ") VALUES (" . implode(',', $vals) . ")";
            if ($conn->query($sql)) echo json_encode(['success' => true, 'id' => $conn->insert_id]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Update row ----
        case 'update_row':
            $table  = $conn->real_escape_string($_POST['table']);
            $pk_col = $conn->real_escape_string($_POST['pk_col']);
            $pk_val = $conn->real_escape_string($_POST['pk_val']);
            $data_r = json_decode($_POST['row_data'], true);
            $sets   = [];
            foreach ($data_r as $col => $val) {
                $c      = $conn->real_escape_string($col);
                $sets[] = "`$c` = " . ($val === '' ? 'NULL' : "'" . $conn->real_escape_string($val) . "'");
            }
            $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$pk_col` = '$pk_val'";
            if ($conn->query($sql)) echo json_encode(['success' => true, 'affected' => $conn->affected_rows]);
            else echo json_encode(['error' => $conn->error]);
            break;

        // ---- Delete row ----
        case 'delete_row':
            $table  = $conn->real_escape_string($_POST['table']);
            $pk_col = $conn->real_escape_string($_POST['pk_col']);
            $pk_val = $conn->real_escape_string($_POST['pk_val']);
            $sql    = "DELETE FROM `$table` WHERE `$pk_col` = '$pk_val'";
            if ($conn->query($sql)) echo json_encode(['success' => true]);
            else echo json_encode(['error' => $conn->error]);
            break;

        default:
            echo json_encode(['error' => 'Unknown action']);
    }
    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hospital DB Manager</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="app">

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
      <span>hospital_management</span>
    </div>

    <div class="sidebar-section-label">Tables</div>
    <ul class="table-list" id="tableList">
      <li class="loading-item">Loading...</li>
    </ul>

    <div class="sidebar-actions">
      <button class="btn-sidebar-action btn-blue" onclick="openModal('createTable')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Table
      </button>
      <button class="btn-sidebar-action btn-red" id="btnDropTable" onclick="dropTable()" disabled>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        Drop
      </button>
    </div>
  </aside>

  <!-- ===== MAIN ===== -->
  <main class="main">

    <!-- Toolbar -->
    <div class="toolbar" id="toolbar">
      <div class="toolbar-left">
        <span class="toolbar-table-name" id="toolbarTitle">Select a table</span>
      </div>
      <div class="toolbar-right" id="toolbarActions" style="display:none">
        <button class="btn btn-outline" onclick="openModal('addColumn')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Column
        </button>
        <button class="btn btn-outline" onclick="openModal('addFK')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
          Add FK
        </button>
        <button class="btn btn-outline" onclick="openModal('addIndex')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          Add Index
        </button>
        <button class="btn btn-outline" onclick="openModal('renameTable')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Rename
        </button>
        <button class="btn btn-outline btn-sql" onclick="openModal('rawSQL')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
          SQL
        </button>
        <button class="btn btn-red-outline" onclick="truncateTable()">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
          Truncate
        </button>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs" id="tabs" style="display:none">
      <button class="tab active" data-tab="structure" onclick="switchTab('structure', this)">Structure</button>
      <button class="tab" data-tab="browse" onclick="switchTab('browse', this)">
        Browse Data <span class="tab-count" id="tabRowCount"></span>
      </button>
      <button class="tab" data-tab="relations" onclick="switchTab('relations', this)">Relations / FK</button>
      <button class="tab" data-tab="indexes" onclick="switchTab('indexes', this)">Indexes</button>
    </div>

    <!-- Content Area -->
    <div class="content" id="mainContent">
      <div class="empty-state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" opacity="0.25"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <p>Select a table from the sidebar to manage it</p>
        <p style="font-size:12px;color:#aaa;margin-top:4px">Tip: click "New Table" to create one</p>
      </div>
    </div>

  </main>
</div>

<!-- ===== TOAST ===== -->
<div class="toast" id="toast"></div>

<!-- ===== MODAL ===== -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal()">
  <div class="modal-box" id="modalBox" onclick="event.stopPropagation()">
    <div class="modal-header">
      <span id="modalTitle">Modal</span>
      <button class="modal-close" onclick="closeModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" id="modalBody"></div>
  </div>
</div>

<script>
// ============================================================
//  Global state
// ============================================================
let activeTable = null;
let activeTab   = 'structure';
let allTables   = [];
let tableStructure = {};
let browseData  = { page: 1, limit: 25, search: '', total: 0, pages: 1, columns: [], pkCol: null };

// ============================================================
//  Boot
// ============================================================
window.onload = () => loadTables();

async function api(data) {
  const fd = new FormData();
  for (const k in data) fd.append(k, data[k]);
  const res = await fetch('index.php', { method: 'POST', body: fd });
  return res.json();
}

// ============================================================
//  Sidebar — tables
// ============================================================
async function loadTables() {
  const data = await api({ action: 'get_tables' });
  allTables = data.tables || [];
  renderSidebar();
}

function renderSidebar() {
  const ul = document.getElementById('tableList');
  ul.innerHTML = '';
  if (!allTables.length) {
    ul.innerHTML = '<li class="loading-item" style="color:#aaa">No tables found</li>';
    return;
  }
  allTables.forEach(t => {
    const li = document.createElement('li');
    li.className = 'table-item' + (t === activeTable ? ' active' : '');
    li.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="9" x2="9" y2="21"/></svg>${t}`;
    li.onclick = () => selectTable(t);
    ul.appendChild(li);
  });
  document.getElementById('btnDropTable').disabled = !activeTable;
}

// ============================================================
//  Select table
// ============================================================
async function selectTable(t) {
  activeTable = t;
  activeTab   = 'structure';
  browseData  = { page: 1, limit: 25, search: '', total: 0, pages: 1, columns: [], pkCol: null };
  document.getElementById('toolbarTitle').textContent = t;
  document.getElementById('toolbarActions').style.display = 'flex';
  document.getElementById('tabs').style.display = 'flex';
  document.querySelectorAll('.tab').forEach(el => el.classList.toggle('active', el.dataset.tab === 'structure'));
  const badge = document.getElementById('tabRowCount');
  if (badge) badge.textContent = '';
  renderSidebar();
  await loadStructure();
  // pre-fetch row count for badge
  api({ action: 'get_row_count', table: t }).then(d => {
    const b = document.getElementById('tabRowCount');
    if (b && d.count > 0) b.textContent = d.count;
  });
}

async function loadStructure() {
  document.getElementById('mainContent').innerHTML = '<div class="loading-spinner">Loading…</div>';
  const data = await api({ action: 'get_structure', table: activeTable });
  tableStructure = data;
  if (activeTab === 'structure') renderStructure(data);
  else if (activeTab === 'relations') renderRelations(data);
  else renderIndexes(data);
}

// ============================================================
//  Tab switcher
// ============================================================
function switchTab(tab, el) {
  activeTab = tab;
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  if (tab === 'structure') renderStructure(tableStructure);
  else if (tab === 'relations') renderRelations(tableStructure);
  else if (tab === 'indexes') renderIndexes(tableStructure);
  else if (tab === 'browse') { browseData.page = 1; loadBrowse(); }
}

// ============================================================
//  Render: Structure
// ============================================================
function renderStructure(data) {
  const cols = data.columns || [];
  const fks  = data.foreign_keys || {};
  if (!cols.length) {
    document.getElementById('mainContent').innerHTML = '<div class="empty-state"><p>No columns found.</p></div>';
    return;
  }

  let html = `<div class="table-wrap"><table class="data-table">
    <thead><tr>
      <th>#</th><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th><th>Actions</th>
    </tr></thead><tbody>`;

  cols.forEach((c, i) => {
    const isPK  = c.Key === 'PRI';
    const isFK  = !!fks[c.Field];
    const isUNI = c.Key === 'UNI';
    const isMUL = c.Key === 'MUL';

    let keys = '';
    if (isPK)  keys += '<span class="badge badge-pk">PK</span>';
    if (isFK)  keys += `<span class="badge badge-fk" title="${fks[c.Field].REFERENCED_TABLE_NAME}.${fks[c.Field].REFERENCED_COLUMN_NAME}">FK</span>`;
    if (isUNI) keys += '<span class="badge badge-uni">UNI</span>';
    if (isMUL) keys += '<span class="badge badge-idx">IDX</span>';

    html += `<tr>
      <td class="col-num">${i+1}</td>
      <td class="col-name">${c.Field}</td>
      <td><code>${c.Type}</code></td>
      <td class="${c.Null==='YES'?'null-yes':'null-no'}">${c.Null}</td>
      <td>${keys}</td>
      <td class="col-default">${c.Default !== null ? c.Default : '<span class="null-tag">NULL</span>'}</td>
      <td class="col-extra">${c.Extra || ''}</td>
      <td class="col-actions">
        ${!isPK ? `<button class="action-btn btn-edit" title="Edit column" onclick="editColumn('${c.Field}','${c.Type}','${c.Null}','${c.Default||''}','${c.Extra}')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>` : ''}
        ${!isPK ? `<button class="action-btn btn-del" title="Drop column" onclick="dropColumn('${c.Field}')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>` : '<span class="pk-lock" title="Primary key — cannot be removed">🔑</span>'}
      </td>
    </tr>`;
  });

  html += '</tbody></table></div>';
  document.getElementById('mainContent').innerHTML = html;
}

// ============================================================
//  Render: Relations / FK
// ============================================================
function renderRelations(data) {
  const fks = data.foreign_keys || {};
  const keys = Object.keys(fks);

  let html = '<div class="panel-section">';
  if (!keys.length) {
    html += '<div class="empty-state" style="min-height:160px"><p>No foreign keys on this table.</p><p style="font-size:12px;color:#aaa;margin-top:4px">Click "Add FK" in the toolbar to add one.</p></div>';
  } else {
    html += `<table class="data-table"><thead><tr>
      <th>Column</th><th>References</th><th>On Delete</th><th>On Update</th><th>Constraint Name</th><th>Action</th>
    </tr></thead><tbody>`;
    keys.forEach(col => {
      const fk = fks[col];
      html += `<tr>
        <td class="col-name">${col}</td>
        <td><span class="ref-tag">${fk.REFERENCED_TABLE_NAME}</span>.<span class="ref-col">${fk.REFERENCED_COLUMN_NAME}</span></td>
        <td><span class="cascade-badge cascade-${fk.DELETE_RULE.toLowerCase().replace(' ','')}">${fk.DELETE_RULE}</span></td>
        <td><span class="cascade-badge cascade-${fk.UPDATE_RULE.toLowerCase().replace(' ','')}">${fk.UPDATE_RULE}</span></td>
        <td><code style="font-size:11px">${fk.CONSTRAINT_NAME}</code></td>
        <td><button class="action-btn btn-del" onclick="dropFK('${fk.CONSTRAINT_NAME}')" title="Remove FK">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button></td>
      </tr>`;
    });
    html += '</tbody></table>';
  }
  html += '</div>';
  document.getElementById('mainContent').innerHTML = html;
}

// ============================================================
//  Render: Indexes
// ============================================================
function renderIndexes(data) {
  const idxs = data.indexes || [];
  let html = '<div class="panel-section">';
  if (!idxs.length) {
    html += '<div class="empty-state" style="min-height:160px"><p>No indexes found.</p></div>';
  } else {
    html += `<table class="data-table"><thead><tr>
      <th>Index Name</th><th>Column</th><th>Type</th><th>Unique</th><th>Seq</th><th>Action</th>
    </tr></thead><tbody>`;
    idxs.forEach(idx => {
      const isPrimary = idx.Key_name === 'PRIMARY';
      html += `<tr>
        <td><code>${idx.Key_name}</code></td>
        <td class="col-name">${idx.Column_name}</td>
        <td>${idx.Index_type}</td>
        <td>${idx.Non_unique === '0' ? '<span class="badge badge-uni">UNIQUE</span>' : '—'}</td>
        <td>${idx.Seq_in_index}</td>
        <td>${!isPrimary ? `<button class="action-btn btn-del" onclick="dropIndex('${idx.Key_name}')" title="Drop index">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>` : '<span style="color:#aaa;font-size:11px">primary</span>'}</td>
      </tr>`;
    });
    html += '</tbody></table>';
  }
  html += '</div>';
  document.getElementById('mainContent').innerHTML = html;
}

// ============================================================
//  Modals
// ============================================================
const DATA_TYPES = ['INT','BIGINT','SMALLINT','TINYINT','VARCHAR(50)','VARCHAR(100)','VARCHAR(255)',
  'TEXT','LONGTEXT','DATE','DATETIME','TIMESTAMP','DECIMAL(10,2)','FLOAT','DOUBLE',
  'BOOLEAN','TINYINT(1)','ENUM(\'\',\'\')','JSON'];

function typeOptions(selected='') {
  return DATA_TYPES.map(t => `<option${t===selected?' selected':''}>${t}</option>`).join('');
}

function openModal(type, extra={}) {
  const overlay = document.getElementById('modalOverlay');
  const title   = document.getElementById('modalTitle');
  const body    = document.getElementById('modalBody');
  overlay.classList.add('open');

  if (type === 'addColumn') {
    title.textContent = `Add Column — ${activeTable}`;
    body.innerHTML = `
      <div class="form-group"><label>Column name</label><input type="text" id="fc_name" placeholder="e.g. email" autofocus></div>
      <div class="form-group"><label>Data type</label><select id="fc_type">${typeOptions()}</select></div>
      <div class="form-group"><label>Default value <span class="label-hint">(leave blank for NULL)</span></label><input type="text" id="fc_default" placeholder="NULL"></div>
      <div class="form-group">
        <label>Options</label>
        <div class="checkbox-group">
          <label><input type="checkbox" id="fc_nn"> NOT NULL</label>
          <label><input type="checkbox" id="fc_unique"> UNIQUE</label>
          <label><input type="checkbox" id="fc_ai"> AUTO_INCREMENT</label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button class="btn btn-blue" onclick="submitAddColumn()">Add Column</button>
      </div>`;
  }

  else if (type === 'editColumn') {
    title.textContent = `Edit Column — ${extra.name}`;
    body.innerHTML = `
      <div class="form-group"><label>Column name</label><input type="text" id="ec_name" value="${extra.name}"></div>
      <div class="form-group"><label>Data type</label><select id="ec_type">${typeOptions(extra.type)}<option ${!DATA_TYPES.includes(extra.type)?'selected':''}>${extra.type}</option></select></div>
      <div class="form-group"><label>Default value</label><input type="text" id="ec_default" value="${extra.def||''}"></div>
      <div class="form-group">
        <label>Options</label>
        <div class="checkbox-group">
          <label><input type="checkbox" id="ec_nn" ${extra.nullable==='NO'?'checked':''}> NOT NULL</label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button class="btn btn-blue" onclick="submitEditColumn('${extra.name}')">Save Changes</button>
      </div>`;
  }

  else if (type === 'addFK') {
    const nonPK = (tableStructure.columns||[]).filter(c=>c.Key!=='PRI');
    const colOpts = nonPK.map(c=>`<option>${c.Field}</option>`).join('');
    const tableOpts = allTables.filter(t=>t!==activeTable).map(t=>`<option>${t}</option>`).join('');
    const cascade = ['CASCADE','RESTRICT','SET NULL','NO ACTION'];
    const cascOpts = cascade.map(c=>`<option>${c}</option>`).join('');
    title.textContent = `Add Foreign Key — ${activeTable}`;
    body.innerHTML = `
      <div class="form-group"><label>Column (in ${activeTable})</label><select id="fk_col">${colOpts}</select></div>
      <div class="form-group"><label>References table</label><select id="fk_reftable" onchange="loadRefCols()">${tableOpts}</select></div>
      <div class="form-group"><label>References column</label><select id="fk_refcol"></select></div>
      <div class="form-row-2">
        <div class="form-group"><label>On Delete</label><select id="fk_del">${cascOpts}</select></div>
        <div class="form-group"><label>On Update</label><select id="fk_upd">${cascOpts}</select></div>
      </div>
      <div class="sql-preview" id="fkPreview"></div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button class="btn btn-blue" onclick="submitAddFK()">Add Foreign Key</button>
      </div>`;
    loadRefCols();
    document.getElementById('fk_col').addEventListener('change', updateFKPreview);
    document.getElementById('fk_del').addEventListener('change', updateFKPreview);
    document.getElementById('fk_upd').addEventListener('change', updateFKPreview);
  }

  else if (type === 'addIndex') {
    const colOpts = (tableStructure.columns||[]).map(c=>`<option>${c.Field}</option>`).join('');
    title.textContent = `Add Index — ${activeTable}`;
    body.innerHTML = `
      <div class="form-group"><label>Column</label><select id="idx_col">${colOpts}</select></div>
      <div class="form-group"><label>Index type</label>
        <select id="idx_type">
          <option value="INDEX">INDEX (regular)</option>
          <option value="UNIQUE">UNIQUE</option>
          <option value="FULLTEXT">FULLTEXT</option>
        </select>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button class="btn btn-blue" onclick="submitAddIndex()">Add Index</button>
      </div>`;
  }

  else if (type === 'createTable') {
    title.textContent = 'Create New Table';
    body.innerHTML = `
      <div class="form-group"><label>Table name</label><input type="text" id="nt_name" placeholder="e.g. MedicalRecord" autofocus></div>
      <div class="form-group"><label>Primary key column name</label><input type="text" id="nt_pk" value="id" placeholder="id"></div>
      <div class="form-group"><label>Storage engine</label>
        <select id="nt_engine"><option>InnoDB</option><option>MyISAM</option><option>MEMORY</option></select>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button class="btn btn-blue" onclick="submitCreateTable()">Create Table</button>
      </div>`;
  }

  else if (type === 'renameTable') {
    title.textContent = `Rename Table — ${activeTable}`;
    body.innerHTML = `
      <div class="form-group"><label>New table name</label><input type="text" id="rt_name" value="${activeTable}" autofocus></div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button class="btn btn-blue" onclick="submitRenameTable()">Rename</button>
      </div>`;
  }

  else if (type === 'insertRow') {
    overlay.classList.add('open');
    buildInsertModal(null);
    return;
  }

  else if (type === 'editRow') {
    overlay.classList.add('open');
    buildInsertModal(extra);
    return;
  }

  else if (type === 'rawSQL') {
    title.textContent = 'Run Raw SQL';
    body.innerHTML = `
      <div class="form-group">
        <label>SQL statement <span class="label-hint">(runs against hospital_management)</span></label>
        <textarea id="raw_sql" rows="6" placeholder="SELECT * FROM Patient LIMIT 10;" style="font-family:monospace;font-size:13px"></textarea>
      </div>
      <div id="sql_result" class="sql-result"></div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">Close</button>
        <button class="btn btn-blue" onclick="submitRawSQL()">Run SQL</button>
      </div>`;
  }
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
}

// ============================================================
//  FK ref column loader + preview
// ============================================================
async function loadRefCols() {
  const t = document.getElementById('fk_reftable')?.value;
  if (!t) return;
  const data = await api({ action: 'get_columns', table: t });
  const sel = document.getElementById('fk_refcol');
  if (!sel) return;
  sel.innerHTML = (data.columns||[]).map(c=>`<option>${c}</option>`).join('');
  updateFKPreview();
}

function updateFKPreview() {
  const col    = document.getElementById('fk_col')?.value;
  const refT   = document.getElementById('fk_reftable')?.value;
  const refC   = document.getElementById('fk_refcol')?.value;
  const onDel  = document.getElementById('fk_del')?.value;
  const onUpd  = document.getElementById('fk_upd')?.value;
  const preview = document.getElementById('fkPreview');
  if (preview && col && refT && refC)
    preview.textContent = `FOREIGN KEY (\`${col}\`) REFERENCES \`${refT}\`(\`${refC}\`) ON DELETE ${onDel} ON UPDATE ${onUpd}`;
}

// ============================================================
//  Column: add / edit / drop
// ============================================================
async function submitAddColumn() {
  const name = document.getElementById('fc_name').value.trim();
  if (!name) { showToast('Column name is required', 'error'); return; }
  const data = await api({
    action: 'add_column', table: activeTable,
    col_name: name,
    col_type: document.getElementById('fc_type').value,
    default_val: document.getElementById('fc_default').value,
    not_null: document.getElementById('fc_nn').checked ? '1' : '0',
    unique_col: document.getElementById('fc_unique').checked ? '1' : '0',
    auto_inc: document.getElementById('fc_ai').checked ? '1' : '0',
  });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast(`Column "${name}" added!`, 'success');
  closeModal(); loadStructure();
}

function editColumn(name, type, nullable, def, extra) {
  openModal('editColumn', { name, type, nullable, def, extra });
}

async function submitEditColumn(oldName) {
  const data = await api({
    action: 'edit_column', table: activeTable,
    old_name: oldName,
    col_name: document.getElementById('ec_name').value.trim(),
    col_type: document.getElementById('ec_type').value,
    default_val: document.getElementById('ec_default').value,
    not_null: document.getElementById('ec_nn').checked ? '1' : '0',
  });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast('Column updated!', 'success');
  closeModal(); loadStructure();
}

async function dropColumn(col) {
  if (!confirm(`Drop column "${col}" from ${activeTable}? This cannot be undone.`)) return;
  const data = await api({ action: 'drop_column', table: activeTable, col_name: col });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast(`Column "${col}" dropped`, 'success');
  loadStructure();
}

// ============================================================
//  FK: add / drop
// ============================================================
async function submitAddFK() {
  const data = await api({
    action: 'add_fk', table: activeTable,
    col: document.getElementById('fk_col').value,
    ref_table: document.getElementById('fk_reftable').value,
    ref_col: document.getElementById('fk_refcol').value,
    on_delete: document.getElementById('fk_del').value,
    on_update: document.getElementById('fk_upd').value,
  });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast('Foreign key added!', 'success');
  closeModal(); loadStructure();
}

async function dropFK(fkName) {
  if (!confirm(`Remove foreign key "${fkName}"?`)) return;
  const data = await api({ action: 'drop_fk', table: activeTable, fk_name: fkName });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast('Foreign key removed', 'success');
  loadStructure();
}

// ============================================================
//  Index: add / drop
// ============================================================
async function submitAddIndex() {
  const data = await api({
    action: 'add_index', table: activeTable,
    col: document.getElementById('idx_col').value,
    idx_type: document.getElementById('idx_type').value,
  });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast('Index added!', 'success');
  closeModal(); loadStructure();
}

async function dropIndex(idxName) {
  if (!confirm(`Drop index "${idxName}"?`)) return;
  const data = await api({ action: 'drop_index', table: activeTable, idx_name: idxName });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast('Index dropped', 'success');
  loadStructure();
}

// ============================================================
//  Table: create / drop / truncate / rename
// ============================================================
async function submitCreateTable() {
  const name = document.getElementById('nt_name').value.trim();
  if (!name) { showToast('Table name is required', 'error'); return; }
  const data = await api({
    action: 'create_table', table_name: name,
    pk_name: document.getElementById('nt_pk').value.trim() || 'id',
    engine: document.getElementById('nt_engine').value,
  });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast(`Table "${name}" created!`, 'success');
  closeModal(); await loadTables(); selectTable(name);
}

async function dropTable() {
  if (!activeTable) return;
  if (!confirm(`DROP TABLE "${activeTable}"? ALL DATA WILL BE LOST.`)) return;
  if (!confirm(`Are you absolutely sure? This is irreversible.`)) return;
  const data = await api({ action: 'drop_table', table: activeTable });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast(`Table "${activeTable}" dropped`, 'success');
  activeTable = null;
  document.getElementById('toolbarTitle').textContent = 'Select a table';
  document.getElementById('toolbarActions').style.display = 'none';
  document.getElementById('tabs').style.display = 'none';
  document.getElementById('mainContent').innerHTML = '<div class="empty-state"><p>Table dropped. Select another table.</p></div>';
  await loadTables();
}

async function truncateTable() {
  if (!activeTable) return;
  if (!confirm(`TRUNCATE "${activeTable}"? All rows will be deleted.`)) return;
  const data = await api({ action: 'truncate_table', table: activeTable });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast(`"${activeTable}" truncated — all rows deleted`, 'success');
}

async function submitRenameTable() {
  const newName = document.getElementById('rt_name').value.trim();
  if (!newName || newName === activeTable) { closeModal(); return; }
  const data = await api({ action: 'rename_table', old_name: activeTable, new_name: newName });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast(`Renamed to "${newName}"`, 'success');
  closeModal();
  const prev = activeTable;
  activeTable = newName;
  await loadTables();
  selectTable(newName);
}

// ============================================================
//  Raw SQL
// ============================================================
async function submitRawSQL() {
  const sql = document.getElementById('raw_sql').value.trim();
  if (!sql) return;
  const data = await api({ action: 'run_sql', sql });
  const out  = document.getElementById('sql_result');
  if (data.error) {
    out.innerHTML = `<div class="sql-error">Error: ${data.error}</div>`;
    return;
  }
  if (data.rows) {
    let tbl = `<table class="sql-out-table"><thead><tr>${data.fields.map(f=>`<th>${f}</th>`).join('')}</tr></thead><tbody>`;
    data.rows.forEach(r => { tbl += '<tr>' + data.fields.map(f=>`<td>${r[f]??'NULL'}</td>`).join('') + '</tr>'; });
    tbl += '</tbody></table>';
    out.innerHTML = `<div class="sql-success">✓ ${data.rows.length} row(s) returned</div>${tbl}`;
  } else {
    out.innerHTML = `<div class="sql-success">✓ Query OK — ${data.affected} row(s) affected</div>`;
    loadTables();
  }
}

// ============================================================
//  BROWSE DATA
// ============================================================
async function loadBrowse() {
  document.getElementById('mainContent').innerHTML = '<div class="loading-spinner">Loading rows…</div>';
  const data = await api({
    action: 'browse_data',
    table:  activeTable,
    page:   browseData.page,
    limit:  browseData.limit,
    search: browseData.search,
  });
  if (data.error) {
    document.getElementById('mainContent').innerHTML = `<div class="empty-state"><p style="color:var(--red)">${data.error}</p></div>`;
    return;
  }
  browseData.total   = data.total;
  browseData.pages   = data.pages;
  browseData.columns = data.columns;
  browseData.pkCol   = data.columns[0]; // first col assumed PK

  // Update tab count badge
  const badge = document.getElementById('tabRowCount');
  if (badge) badge.textContent = data.total > 0 ? data.total : '';

  renderBrowse(data);
}

function renderBrowse(data) {
  const cols = data.columns;
  const rows = data.rows;

  // Toolbar row: search + insert + pagination
  let html = `<div class="browse-toolbar">
    <div class="browse-search-wrap">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="browseSearch" placeholder="Search all columns…" value="${escHtml(browseData.search)}"
        oninput="browseData.search=this.value;browseData.page=1;loadBrowse()" class="browse-search-input">
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <select class="browse-limit-sel" onchange="browseData.limit=parseInt(this.value);browseData.page=1;loadBrowse()">
        ${[10,25,50,100].map(n=>`<option value="${n}"${n===browseData.limit?' selected':''}>${n} rows</option>`).join('')}
      </select>
      <button class="btn btn-blue" onclick="openModal('insertRow')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Insert Row
      </button>
    </div>
  </div>`;

  if (!rows.length) {
    html += '<div class="empty-state" style="min-height:200px"><p>No rows found.</p><p style="font-size:12px;color:#aaa;margin-top:4px">Click "Insert Row" to add data.</p></div>';
  } else {
    html += `<div class="table-wrap"><table class="data-table browse-table">
      <thead><tr>
        <th class="browse-actions-th">Actions</th>
        ${cols.map(c => `<th>${escHtml(c)}</th>`).join('')}
      </tr></thead><tbody>`;

    rows.forEach(row => {
      const pkVal = row[browseData.pkCol];
      const rowJson = escHtml(JSON.stringify(row));
      html += `<tr>
        <td class="browse-row-actions">
          <button class="action-btn btn-edit" title="Edit row" onclick='openEditRow(${JSON.stringify(row)})'>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <button class="action-btn btn-del" title="Delete row" onclick="deleteRow('${escHtml(String(pkVal))}')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          </button>
        </td>
        ${cols.map(c => {
          const v = row[c];
          if (v === null) return `<td class="cell-null">NULL</td>`;
          const s = String(v);
          return `<td class="cell-val" title="${escHtml(s)}">${escHtml(s.length > 60 ? s.slice(0,60)+'…' : s)}</td>`;
        }).join('')}
      </tr>`;
    });
    html += '</tbody></table></div>';
  }

  // Pagination
  if (data.pages > 1) {
    const p = data.page, pp = data.pages;
    html += `<div class="pagination">
      <span class="page-info">Page ${p} of ${pp} &nbsp;·&nbsp; ${data.total} rows</span>
      <div class="page-btns">
        <button class="page-btn" onclick="browseData.page=1;loadBrowse()" ${p<=1?'disabled':''}>«</button>
        <button class="page-btn" onclick="browseData.page=${p-1};loadBrowse()" ${p<=1?'disabled':''}>‹</button>
        ${pageNums(p, pp).map(n => n === '…'
          ? `<span class="page-ellipsis">…</span>`
          : `<button class="page-btn${n===p?' page-active':''}" onclick="browseData.page=${n};loadBrowse()">${n}</button>`
        ).join('')}
        <button class="page-btn" onclick="browseData.page=${p+1};loadBrowse()" ${p>=pp?'disabled':''}>›</button>
        <button class="page-btn" onclick="browseData.page=${pp};loadBrowse()" ${p>=pp?'disabled':''}>»</button>
      </div>
    </div>`;
  } else if (data.total > 0) {
    html += `<div class="pagination"><span class="page-info">${data.total} row${data.total!==1?'s':''}</span></div>`;
  }

  document.getElementById('mainContent').innerHTML = html;
}

function pageNums(cur, total) {
  if (total <= 7) return Array.from({length: total}, (_,i) => i+1);
  const pages = new Set([1, 2, cur-1, cur, cur+1, total-1, total].filter(n => n>=1 && n<=total));
  const arr = [...pages].sort((a,b)=>a-b);
  const out = [];
  arr.forEach((n,i) => { if (i && n - arr[i-1] > 1) out.push('…'); out.push(n); });
  return out;
}

function escHtml(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ============================================================
//  INSERT ROW modal
// ============================================================
// (called from openModal dispatcher below — we hook into it)
function buildInsertModal(editRow = null) {
  const cols = browseData.columns;
  const pkCol = browseData.pkCol;
  const isEdit = !!editRow;
  document.getElementById('modalTitle').textContent = isEdit ? `Edit Row — ${activeTable}` : `Insert Row — ${activeTable}`;

  let fields = '';
  cols.forEach(col => {
    const isPK = col === pkCol;
    const val  = isEdit ? escHtml(editRow[col] ?? '') : '';
    fields += `<div class="form-group">
      <label>${escHtml(col)}${isPK ? ' <span class="badge badge-pk" style="vertical-align:middle">PK</span>' : ''}</label>
      <input type="text" id="rowfield_${escHtml(col)}" value="${val}"
        placeholder="${isPK && !isEdit ? 'Auto (leave blank)' : 'NULL for empty'}"
        ${isPK && !isEdit ? 'style="color:var(--text3)"' : ''}>
    </div>`;
  });

  document.getElementById('modalBody').innerHTML = `
    ${fields}
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button class="btn btn-blue" onclick="${isEdit ? `submitEditRow('${escHtml(String(editRow[pkCol]))}')` : 'submitInsertRow()'}">
        ${isEdit ? 'Save Changes' : 'Insert Row'}
      </button>
    </div>`;
}

async function submitInsertRow() {
  const cols = browseData.columns;
  const pkCol = browseData.pkCol;
  const rowData = {};
  cols.forEach(col => {
    const el = document.getElementById(`rowfield_${col}`);
    if (!el) return;
    if (col === pkCol && el.value.trim() === '') return; // skip PK → AUTO_INCREMENT
    rowData[col] = el.value;
  });
  const data = await api({ action: 'insert_row', table: activeTable, row_data: JSON.stringify(rowData) });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast('Row inserted!', 'success');
  closeModal(); loadBrowse();
}

function openEditRow(row) {
  openModal('editRow', row);
}

async function submitEditRow(pkVal) {
  const cols  = browseData.columns;
  const pkCol = browseData.pkCol;
  const rowData = {};
  cols.forEach(col => {
    const el = document.getElementById(`rowfield_${col}`);
    if (!el || col === pkCol) return;
    rowData[col] = el.value;
  });
  const data = await api({
    action: 'update_row', table: activeTable,
    pk_col: pkCol, pk_val: pkVal,
    row_data: JSON.stringify(rowData)
  });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast('Row updated!', 'success');
  closeModal(); loadBrowse();
}

async function deleteRow(pkVal) {
  if (!confirm(`Delete row where ${browseData.pkCol} = ${pkVal}?`)) return;
  const data = await api({ action: 'delete_row', table: activeTable, pk_col: browseData.pkCol, pk_val: pkVal });
  if (data.error) { showToast(data.error, 'error'); return; }
  showToast('Row deleted', 'success');
  loadBrowse();
}

// ============================================================
//  Toast
// ============================================================
let toastTimer;
function showToast(msg, type='success') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className   = 'toast toast-' + type + ' toast-show';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.className = 'toast', 3000);
}
</script>
</body>
</html>