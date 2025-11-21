<?php
// fuel_station.php — Fuel Station Management
// PHP 5.3 compatible

// DEBUG: enable while testing
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

/*
 * Direct DB settings
 */
$DB_HOST = 'mysql056.hosting.combell.com';
$DB_USER = 'ID127947_egl1';
$DB_PASS = 'Plane123';
$DB_NAME = 'ID127947_egl1';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    die('DB connection failed: '.$mysqli->connect_error);
}
$mysqli->set_charset('utf8');

/**
 * Convert HTML5 datetime-local to MySQL DATETIME
 * "2025-11-17T12:34" -> "2025-11-17 12:34:00"
 */
function parse_datetime_local($value) {
    if (!isset($value) || $value === '') {
        return date('Y-m-d H:i:s');
    }
    $value = str_replace('T', ' ', $value);
    if (strlen($value) == 16) {
        $value .= ':00';
    }
    return $value;
}

/**
 * Signature PNG path: fuel_signatures/signature_{id}.png
 */
function fuel_signature_path($sessionId) {
    $sessionId = (int)$sessionId;
    if ($sessionId <= 0) return '';
    $rel  = 'fuel_signatures/signature_' . $sessionId . '.png';
    $full = dirname(__FILE__) . '/' . $rel;
    if (is_file($full)) {
        return $rel;
    }
    return '';
}

/* ---------------------------------------------------------------------
   POST Handlers
   --------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Save delivery --------------------------------------------------- */
    if (isset($_POST['action']) && $_POST['action'] === 'save_delivery') {

        $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $gal  = isset($_POST['gallons']) ? (float)$_POST['gallons'] : 0.0;
        $ppg  = (isset($_POST['price_per_gallon']) && $_POST['price_per_gallon'] !== '') ? (float)$_POST['price_per_gallon'] : null;
        $mpg  = (isset($_POST['margin_per_gallon']) && $_POST['margin_per_gallon'] !== '') ? (float)$_POST['margin_per_gallon'] : null;
        $tax  = (isset($_POST['tax_rate']) && $_POST['tax_rate'] !== '') ? (float)$_POST['tax_rate'] : null;
        $date = parse_datetime_local(isset($_POST['delivered_at']) ? $_POST['delivered_at'] : '');
        $note = isset($_POST['notes']) ? trim($_POST['notes']) : '';

        if ($id > 0) {
            $sql = "UPDATE fuel_deliveries
                    SET gallons=?, price_per_gallon=?, margin_per_gallon=?, tax_rate=?, delivered_at=?, notes=?
                    WHERE id=?";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) die('Prepare failed: '.$mysqli->error);
            // 4 doubles, 2 strings, 1 int
            $stmt->bind_param('ddddssi', $gal, $ppg, $mpg, $tax, $date, $note, $id);
        } else {
            $sql = "INSERT INTO fuel_deliveries
                    (gallons, price_per_gallon, margin_per_gallon, tax_rate, delivered_at, notes, created_at)
                    VALUES (?,?,?,?,?,?,NOW())";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) die('Prepare failed: '.$mysqli->error);
            $stmt->bind_param('ddddss', $gal, $ppg, $mpg, $tax, $date, $note);
        }

        $stmt->execute();
        $stmt->close();
        header('Location: fuel_station.php');
        exit;
    }

    /* Save Config ----------------------------------------------------- */
    if (isset($_POST['action']) && $_POST['action'] === 'save_config') {

        $cap = isset($_POST['tank_capacity_gal']) ? (float)$_POST['tank_capacity_gal'] : 500.0;
        $min = isset($_POST['min_level_gal']) ? (float)$_POST['min_level_gal'] : 100.0;
        $em  = isset($_POST['notify_email']) ? trim($_POST['notify_email']) : '';

        $cfgId = 0;
        $rsCfg = $mysqli->query("SELECT id FROM fuel_station_config LIMIT 1");
        if ($rsCfg) {
            if ($rsCfg->num_rows) {
                $rowCfg = $rsCfg->fetch_assoc();
                $cfgId = (int)$rowCfg['id'];
            }
            $rsCfg->close();
        }

        if ($cfgId > 0) {
            $stmt = $mysqli->prepare("UPDATE fuel_station_config
                SET tank_capacity_gal=?, min_level_gal=?, notify_email=? WHERE id=?");
            if (!$stmt) die('Prepare failed: '.$mysqli->error);
            $stmt->bind_param('ddsi', $cap, $min, $em, $cfgId);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO fuel_station_config
                (tank_capacity_gal, min_level_gal, notify_email) VALUES (?,?,?)");
            if (!$stmt) die('Prepare failed: '.$mysqli->error);
            $stmt->bind_param('dds', $cap, $min, $em);
        }
        $stmt->execute();
        $stmt->close();

        header('Location: fuel_station.php');
        exit;
    }

    /* Save Session (edit) -------------------------------------------- */
    if (isset($_POST['action']) && $_POST['action'] === 'save_session') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $gal = isset($_POST['gallons']) ? (float)$_POST['gallons'] : 0.0;
            $lit = isset($_POST['liters']) ? (float)$_POST['liters'] : 0.0;
            $ppu = (isset($_POST['unit_price']) && $_POST['unit_price'] !== '') ? (float)$_POST['unit_price'] : null;
            $tax = (isset($_POST['tax_rate']) && $_POST['tax_rate'] !== '') ? (float)$_POST['tax_rate'] : null;
            $tot = (isset($_POST['total_usd']) && $_POST['total_usd'] !== '') ? (float)$_POST['total_usd'] : null;

            $sql = "UPDATE fuel_sessions
                    SET gallons=?, liters=?, unit_price=?, tax_rate=?, total_usd=?
                    WHERE id=?";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) die('Prepare failed: '.$mysqli->error);
            $stmt->bind_param('dddddi', $gal, $lit, $ppu, $tax, $tot, $id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: fuel_station.php');
        exit;
    }
}

/* ---------------------------------------------------------------------
   Load Summary
   --------------------------------------------------------------------- */

$delivered = 0.0;
$rs = $mysqli->query("SELECT COALESCE(SUM(gallons),0) AS total FROM fuel_deliveries");
if ($rs) {
    $row = $rs->fetch_assoc();
    if ($row && isset($row['total'])) {
        $delivered = (float)$row['total'];
    }
    $rs->close();
}

$pumped = 0.0;
$rs = $mysqli->query("SELECT COALESCE(SUM(gallons),0) AS total FROM fuel_sessions");
if ($rs) {
    $row = $rs->fetch_assoc();
    if ($row && isset($row['total'])) {
        $pumped = (float)$row['total'];
    }
    $rs->close();
}

$available = max(0.0, $delivered - $pumped);

/* Config defaults */
$config = array(
    'tank_capacity_gal' => 500.0,
    'min_level_gal'     => 100.0,
    'notify_email'      => ''
);

$capacity = $config['tank_capacity_gal'];
$minLevel = $config['min_level_gal'];

$rs = $mysqli->query("SELECT * FROM fuel_station_config LIMIT 1");
if ($rs) {
    if ($rs->num_rows) {
        $row = $rs->fetch_assoc();
        if (isset($row['tank_capacity_gal'])) $config['tank_capacity_gal'] = (float)$row['tank_capacity_gal'];
        if (isset($row['min_level_gal']))     $config['min_level_gal']     = (float)$row['min_level_gal'];
        if (isset($row['notify_email']))      $config['notify_email']      = $row['notify_email'];
    }
    $rs->close();
}

$capacity = (float)$config['tank_capacity_gal'];
$minLevel = (float)$config['min_level_gal'];
$pct      = $capacity > 0 ? max(0, min(100, ($available / $capacity) * 100)) : 0;

/* ---------------------------------------------------------------------
   Lookup users + devices
   --------------------------------------------------------------------- */

$userNames   = array();
$deviceNames = array();

/* Users */
$rs = $mysqli->query("SHOW TABLES LIKE 'users'");
if ($rs) {
    if ($rs->num_rows) {
        $rs->close();
        $rs2 = $mysqli->query("SELECT userid, voornaam, naam FROM users");
        if ($rs2) {
            while ($u = $rs2->fetch_assoc()) {
                $uid = (int)$u['userid'];
                $nm  = trim($u['voornaam'].' '.$u['naam']);
                if ($nm === '') $nm = 'User #'.$uid;
                $userNames[$uid] = $nm;
            }
            $rs2->close();
        }
    } else {
        $rs->close();
    }
}

/* Devices */
$rs = $mysqli->query("SHOW TABLES LIKE 'devices'");
if ($rs) {
    if ($rs->num_rows) {
        $rs->close();
        $rs2 = $mysqli->query("SELECT dev_id, dev_name, dev_type FROM devices");
        if ($rs2) {
            while ($d = $rs2->fetch_assoc()) {
                $did = (int)$d['dev_id'];
                $nm  = $d['dev_name'];
                if (!empty($d['dev_type'])) {
                    $nm .= ' ('.$d['dev_type'].')';
                }
                if ($nm === '') $nm = 'Device #'.$did;
                $deviceNames[$did] = $nm;
            }
            $rs2->close();
        }
    } else {
        $rs->close();
    }
}

/* ---------------------------------------------------------------------
   Load deliveries + sessions
   --------------------------------------------------------------------- */

$deliveries = array();
$rs = $mysqli->query("SELECT * FROM fuel_deliveries ORDER BY delivered_at DESC");
if ($rs) {
    while ($row = $rs->fetch_assoc()) {
        $deliveries[] = $row;
    }
    $rs->close();
}

$sessions = array();
$rs = $mysqli->query("SELECT * FROM fuel_sessions ORDER BY start_time DESC LIMIT 200");
if ($rs) {
    while ($row = $rs->fetch_assoc()) {
        $sessions[] = $row;
    }
    $rs->close();
}

/* Which delivery being edited? */
$editDelivery = null;
if (isset($_GET['edit_delivery'])) {
    $editId = (int)$_GET['edit_delivery'];
    foreach ($deliveries as $d) {
        if ((int)$d['id'] === $editId) {
            $editDelivery = $d;
            break;
        }
    }
}

/* Which session being edited? */
$editSession = null;
if (isset($_GET['edit_session'])) {
    $editId = (int)$_GET['edit_session'];
    foreach ($sessions as $s) {
        if ((int)$s['id'] === $editId) {
            $editSession = $s;
            break;
        }
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Fuel Station Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;background:#eef1f6;color:#1a1f36;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial;}
    .topbar{background:linear-gradient(90deg,#1e3c72,#2a5298);color:#fff;padding:10px 16px;display:flex;align-items:center;gap:12px;}
    .topbar a{color:#fff;text-decoration:none;}
    .wrap{padding:12px 16px;}
    .card{background:#fff;border:1px solid #dde3f0;border-radius:14px;margin-bottom:16px;overflow:hidden;}
    .headerBar{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid #e5e9f2;}
    .spacer{flex:1}
    .pad{padding:12px 16px;}
    .muted{color:#6b7487;}
    .btn{background:#f3f6fb;border:1px solid #cfd5e3;color:#1a1f36;padding:6px 10px;border-radius:8px;cursor:pointer;text-decoration:none;display:inline-block;}
    table{width:100%;border-collapse:collapse;font-size:13px;}
    th,td{padding:6px 8px;border-bottom:1px solid #e5e9f2;text-align:left;}
    th{background:#f8fafc;font-weight:600;}
    input[type=text],input[type=number],input[type=datetime-local]{padding:6px 8px;border:1px solid #cfd5e3;border-radius:6px;width:100%;box-sizing:border-box;}
    label{font-size:13px;font-weight:600;}
    .formRow{margin-bottom:8px;}
  </style>
</head>
<body>
  <div class="topbar">
    <strong>Fuel Station Management</strong>
    <span class="spacer"></span>
    <a href="reminders.php">← Back to Reminders</a>
  </div>

  <div class="wrap">

    <!-- Summary -->
    <div class="card">
      <div class="headerBar">
        <strong>Fuel Summary</strong>
      </div>
      <div class="pad">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
          <div style="flex:1;height:14px;background:#e6e9ef;border-radius:999px;overflow:hidden;">
            <div style="width:<?php echo number_format($pct,1); ?>%;height:100%;background:<?php
              if ($available <= $minLevel) {
                  echo '#e53935';
              } elseif ($available <= $capacity*0.25) {
                  echo '#f59e0b';
              } else {
                  echo '#22c55e';
              }
            ?>;border-radius:999px;"></div>
          </div>
          <div class="muted" style="white-space:nowrap;">
            <?php echo number_format($available,1); ?> / <?php echo number_format($capacity,1); ?> gal
          </div>
        </div>
        <div class="muted">
          Delivered total: <strong><?php echo number_format($delivered,1); ?> gal</strong>,
          Pumped out: <strong><?php echo number_format($pumped,1); ?> gal</strong><br>
          Reorder level: <strong><?php echo number_format($minLevel,1); ?> gal</strong>
          <?php if ($available <= $minLevel): ?>
            <span style="color:#b02323;font-weight:600;"> — BELOW MINIMUM, ORDER FUEL</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Config -->
    <div class="card">
      <div class="headerBar">
        <strong>Fuel Alert &amp; Settings</strong>
      </div>
      <div class="pad">
        <form method="post">
          <input type="hidden" name="action" value="save_config">
          <div style="display:grid;grid-template-columns:1fr 1fr 1.5fr;gap:10px 16px;">
            <div class="formRow">
              <label>Tank Capacity (gal)</label>
              <input type="number" step="0.1" name="tank_capacity_gal"
                     value="<?php echo htmlspecialchars($capacity,ENT_QUOTES,'UTF-8'); ?>">
            </div>
            <div class="formRow">
              <label>Minimum Level (gal)</label>
              <input type="number" step="0.1" name="min_level_gal"
                     value="<?php echo htmlspecialchars($minLevel,ENT_QUOTES,'UTF-8'); ?>">
            </div>
            <div class="formRow">
              <label>Alert E-mail (optional)</label>
              <input type="text" name="notify_email"
                     value="<?php echo htmlspecialchars($config['notify_email'],ENT_QUOTES,'UTF-8'); ?>">
            </div>
          </div>
          <div style="margin-top:10px;">
            <button class="btn" type="submit">Save Settings</button>
            <span class="muted" style="margin-left:8px;">(Use a cron or daily script to check level and send e-mail when below minimum.)</span>
          </div>
        </form>
      </div>
    </div>

    <!-- Deliveries -->
    <div class="card">
      <div class="headerBar">
        <strong>Fuel Deliveries</strong>
        <span class="spacer"></span>
        <a href="fuel_station.php" class="btn">+ New Delivery</a>
      </div>
      <div class="pad">
        <form method="post" style="margin-bottom:16px;">
          <input type="hidden" name="action" value="save_delivery">
          <?php if ($editDelivery): ?>
            <input type="hidden" name="id" value="<?php echo (int)$editDelivery['id']; ?>">
          <?php endif; ?>
          <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px 16px;">
            <div class="formRow">
              <label>Gallons</label>
              <input type="number" step="0.1" name="gallons"
                     value="<?php echo $editDelivery ? htmlspecialchars($editDelivery['gallons'],ENT_QUOTES,'UTF-8') : ''; ?>">
            </div>
            <div class="formRow">
              <label>Price/gal (USD)</label>
              <input type="number" step="0.0001" name="price_per_gallon"
                     value="<?php echo $editDelivery ? htmlspecialchars($editDelivery['price_per_gallon'],ENT_QUOTES,'UTF-8') : ''; ?>">
            </div>
            <div class="formRow">
              <label>Margin/gal (USD)</label>
              <input type="number" step="0.0001" name="margin_per_gallon"
                     value="<?php echo $editDelivery ? htmlspecialchars($editDelivery['margin_per_gallon'],ENT_QUOTES,'UTF-8') : ''; ?>">
            </div>
            <div class="formRow">
              <label>Tax %</label>
              <input type="number" step="0.01" name="tax_rate"
                     value="<?php echo $editDelivery ? htmlspecialchars($editDelivery['tax_rate'],ENT_QUOTES,'UTF-8') : ''; ?>">
            </div>
            <div class="formRow">
              <label>Delivered at</label>
              <input type="datetime-local" name="delivered_at"
                     value="<?php
                       if ($editDelivery && !empty($editDelivery['delivered_at'])) {
                         echo htmlspecialchars(str_replace(' ', 'T', substr($editDelivery['delivered_at'],0,16)),ENT_QUOTES,'UTF-8');
                       }
                     ?>">
            </div>
          </div>
          <div class="formRow" style="margin-top:8px;">
            <label>Notes</label>
            <input type="text" name="notes"
                   value="<?php echo $editDelivery ? htmlspecialchars($editDelivery['notes'],ENT_QUOTES,'UTF-8') : ''; ?>">
          </div>
          <div style="margin-top:8px;">
            <button class="btn" type="submit"><?php echo $editDelivery ? 'Update Delivery' : 'Add Delivery'; ?></button>
          </div>
        </form>

        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Gallons</th>
              <th>Price/gal</th>
              <th>Margin/gal</th>
              <th>Tax %</th>
              <th>Notes</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$deliveries): ?>
              <tr><td colspan="7" class="muted">No deliveries logged yet.</td></tr>
            <?php else: foreach ($deliveries as $d): ?>
              <tr>
                <td><?php echo htmlspecialchars($d['delivered_at'],ENT_QUOTES,'UTF-8'); ?></td>
                <td><?php echo number_format($d['gallons'],1); ?></td>
                <td><?php echo $d['price_per_gallon'] !== null ? number_format($d['price_per_gallon'],4) : ''; ?></td>
                <td><?php echo $d['margin_per_gallon'] !== null ? number_format($d['margin_per_gallon'],4) : ''; ?></td>
                <td><?php echo $d['tax_rate'] !== null ? number_format($d['tax_rate'],2) : ''; ?></td>
                <td><?php echo htmlspecialchars($d['notes'],ENT_QUOTES,'UTF-8'); ?></td>
                <td>
                  <a href="fuel_station.php?edit_delivery=<?php echo (int)$d['id']; ?>"
                     class="btn" style="padding:3px 6px;font-size:11px;">Edit</a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pump sessions -->
    <div class="card">
      <div class="headerBar">
        <strong>Refuelling Sessions (pump-outs)</strong>
      </div>
      <div class="pad" style="overflow-x:auto;">

        <?php if ($editSession): ?>
          <!-- Edit session inline form -->
          <form method="post" style="margin-bottom:16px;">
            <input type="hidden" name="action" value="save_session">
            <input type="hidden" name="id" value="<?php echo (int)$editSession['id']; ?>">
            <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:8px 16px;">
              <div class="formRow">
                <label>User</label>
                <?php
                  $uid   = (int)$editSession['user_id'];
                  $uname = isset($userNames[$uid]) ? $userNames[$uid] : ('User #'.$uid);
                ?>
                <input type="text" value="<?php echo htmlspecialchars($uname,ENT_QUOTES,'UTF-8'); ?>" disabled>
              </div>
              <div class="formRow">
                <label>Device</label>
                <?php
                  $did   = (int)$editSession['device_id'];
                  $dname = isset($deviceNames[$did]) ? $deviceNames[$did] : ('Device #'.$did);
                ?>
                <input type="text" value="<?php echo htmlspecialchars($dname,ENT_QUOTES,'UTF-8'); ?>" disabled>
              </div>
              <div class="formRow">
                <label>Gallons</label>
                <input type="number" step="0.01" name="gallons"
                       value="<?php echo htmlspecialchars($editSession['gallons'],ENT_QUOTES,'UTF-8'); ?>">
              </div>
              <div class="formRow">
                <label>Liters</label>
                <input type="number" step="0.01" name="liters"
                       value="<?php echo htmlspecialchars($editSession['liters'],ENT_QUOTES,'UTF-8'); ?>">
              </div>
              <div class="formRow">
                <label>Unit Price</label>
                <input type="number" step="0.0001" name="unit_price"
                       value="<?php echo $editSession['unit_price'] !== null ? htmlspecialchars($editSession['unit_price'],ENT_QUOTES,'UTF-8') : ''; ?>">
              </div>
              <div class="formRow">
                <label>Tax %</label>
                <input type="number" step="0.01" name="tax_rate"
                       value="<?php echo $editSession['tax_rate'] !== null ? htmlspecialchars($editSession['tax_rate'],ENT_QUOTES,'UTF-8') : ''; ?>">
              </div>
              <div class="formRow">
                <label>Total USD</label>
                <input type="number" step="0.01" name="total_usd"
                       value="<?php echo $editSession['total_usd'] !== null ? htmlspecialchars($editSession['total_usd'],ENT_QUOTES,'UTF-8') : ''; ?>">
              </div>
            </div>
            <div style="margin-top:8px;">
              <button class="btn" type="submit">Save Session</button>
              <a href="fuel_station.php" class="btn" style="margin-left:6px;">Cancel</a>
            </div>
          </form>
        <?php endif; ?>

        <table>
          <thead>
            <tr>
              <th>Start</th>
              <th>End</th>
              <th>User</th>
              <th>Device</th>
              <th>Gallons</th>
              <th>Liters</th>
              <th>Unit Price</th>
              <th>Tax %</th>
              <th>Total USD</th>
              <th>Signature</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$sessions): ?>
              <tr><td colspan="11" class="muted">No fuel sessions logged yet.</td></tr>
            <?php else: foreach ($sessions as $s): ?>
              <?php
                $uid   = (int)$s['user_id'];
                $did   = (int)$s['device_id'];
                $uname = isset($userNames[$uid])   ? $userNames[$uid]   : ('User #'.$uid);
                $dname = isset($deviceNames[$did]) ? $deviceNames[$did] : ('Device #'.$did);
                $hasSig = !empty($s['has_signature']);
                $sigPath = $hasSig ? fuel_signature_path($s['id']) : '';
              ?>
              <tr>
                <td><?php echo htmlspecialchars($s['start_time'],ENT_QUOTES,'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($s['end_time'],ENT_QUOTES,'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($uname,ENT_QUOTES,'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($dname,ENT_QUOTES,'UTF-8'); ?></td>
                <td><?php echo number_format($s['gallons'],2); ?></td>
                <td><?php echo number_format($s['liters'],2); ?></td>
                <td><?php echo $s['unit_price'] !== null ? number_format($s['unit_price'],4) : ''; ?></td>
                <td><?php echo $s['tax_rate'] !== null ? number_format($s['tax_rate'],2) : ''; ?></td>
                <td><?php echo $s['total_usd'] !== null ? number_format($s['total_usd'],2) : ''; ?></td>
                <td style="text-align:center;">
                  <?php if ($hasSig && $sigPath !== ''): ?>
                    <a href="<?php echo htmlspecialchars($sigPath,ENT_QUOTES,'UTF-8'); ?>" target="_blank">
                      <img src="<?php echo htmlspecialchars($sigPath,ENT_QUOTES,'UTF-8'); ?>"
                           alt="Signature"
                           style="height:32px;max-width:80px;border-radius:4px;border:1px solid #dde3f0;">
                    </a>
                  <?php elseif ($hasSig): ?>
                    &#10003;
                  <?php else: ?>
                    &mdash;
                  <?php endif; ?>
                </td>
                <td>
                  <a href="fuel_station.php?edit_session=<?php echo (int)$s['id']; ?>"
                     class="btn" style="padding:3px 6px;font-size:11px;">Edit</a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</body>
</html>