<?php
/************************************************************
 * fuel_api_session.php — PHP 5.3 compatible
 * Stores one fuel session in fuel_sessions table.
 *
 * Expected POST fields:
 *   api_key
 *   user_id
 *   device_id
 *   gallons
 *   liters
 *   start_time   (YYYY-MM-DD HH:MM:SS)
 *   end_time     (YYYY-MM-DD HH:MM:SS)
 *   pre_checks   (pipe-separated string, e.g. "PWR_OFF|EXTINGUISHER")
 *   post_checks  (pipe-separated string)
 *   unit_price   (string/decimal)
 *   tax_rate     (string/decimal)
 *   total_usd    (string/decimal)
 *   image        (optional, base64 JPEG – currently unused)
 *   signature    (optional, base64 PNG)
 ************************************************************/

header('Content-Type: application/json; charset=utf-8');

// ---- CONFIG -------------------------------------------------

$db_host = "mysql056.hosting.combell.com";
$db_user = "ID127947_egl1";
$db_pass = "Plane123";
$db_name = "ID127947_egl1";

$API_KEY = 'F9K7T2R8M4Q1B6X0H3W2C8N5Y4D9P';

// ---- HELPERS -----------------------------------------------

function json_exit($arr) {
    echo json_encode($arr);
    exit;
}

// ---- VALIDATE METHOD & API KEY ------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(array('ok' => 0, 'error' => 'POST required'));
}

$api_key = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
if ($api_key !== $GLOBALS['API_KEY']) {
    json_exit(array('ok' => 0, 'error' => 'Invalid API key'));
}

// ---- READ & SANITIZE INPUT ---------------------------------

$user_id    = isset($_POST['user_id'])    ? (int)$_POST['user_id']    : 0;
$device_id  = isset($_POST['device_id'])  ? (int)$_POST['device_id']  : 0;
$gallons    = isset($_POST['gallons'])    ? trim($_POST['gallons'])   : '0.00';
$liters     = isset($_POST['liters'])     ? trim($_POST['liters'])    : '0.00';
$start_time = isset($_POST['start_time']) ? trim($_POST['start_time']): '';
$end_time   = isset($_POST['end_time'])   ? trim($_POST['end_time'])  : '';
$pre_checks = isset($_POST['pre_checks']) ? trim($_POST['pre_checks']): '';
$post_checks= isset($_POST['post_checks'])? trim($_POST['post_checks']): '';

$unit_price = isset($_POST['unit_price']) ? trim($_POST['unit_price']) : null;
$tax_rate   = isset($_POST['tax_rate'])   ? trim($_POST['tax_rate'])   : null;
$total_usd  = isset($_POST['total_usd'])  ? trim($_POST['total_usd'])  : null;

$image_b64     = isset($_POST['image'])     ? $_POST['image']     : '';
$signature_b64 = isset($_POST['signature']) ? $_POST['signature'] : '';

$has_photo     = ($image_b64     !== '') ? 1 : 0;
$has_signature = ($signature_b64 !== '') ? 1 : 0;

$created_at = date('Y-m-d H:i:s'); // server local time

// Minimal validation
if ($user_id <= 0 || $device_id <= 0) {
    json_exit(array('ok' => 0, 'error' => 'Missing or invalid user_id / device_id'));
}
if ($start_time === '' || $end_time === '') {
    json_exit(array('ok' => 0, 'error' => 'Missing start_time or end_time'));
}

// ---- CONNECT DB ---------------------------------------------

$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    json_exit(array('ok' => 0, 'error' => 'DB connection failed: '.$mysqli->connect_error));
}
$mysqli->set_charset('utf8');

// ---- INSERT SESSION -----------------------------------------

$sql = "
    INSERT INTO fuel_sessions
    (
        user_id,
        device_id,
        gallons,
        liters,
        start_time,
        end_time,
        pre_checks,
        post_checks,
        has_photo,
        has_signature,
        unit_price,
        tax_rate,
        total_usd,
        created_at
    )
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    json_exit(array('ok' => 0, 'error' => 'Prepare failed: '.$mysqli->error));
}

/*
 We bind everything as strings except the obvious ints.
 MySQL will cast strings to DECIMAL automatically.
*/
$unit_price_str = ($unit_price === null || $unit_price === '') ? null : $unit_price;
$tax_rate_str   = ($tax_rate   === null || $tax_rate   === '') ? null : $tax_rate;
$total_usd_str  = ($total_usd  === null || $total_usd  === '') ? null : $total_usd;

/*
 Types:
   i = int
   s = string
 Order:
   user_id (i)
   device_id (i)
   gallons (s)
   liters (s)
   start_time (s)
   end_time (s)
   pre_checks (s)
   post_checks (s)
   has_photo (i)
   has_signature (i)
   unit_price (s / null)
   tax_rate (s / null)
   total_usd (s / null)
   created_at (s)
*/
$stmt->bind_param(
    "iissssssiiisss",
    $user_id,
    $device_id,
    $gallons,
    $liters,
    $start_time,
    $end_time,
    $pre_checks,
    $post_checks,
    $has_photo,
    $has_signature,
    $unit_price_str,
    $tax_rate_str,
    $total_usd_str,
    $created_at
);

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    json_exit(array('ok' => 0, 'error' => 'Execute failed: '.$err));
}

$sessionId = $stmt->insert_id;
$stmt->close();

// ---- OPTIONAL: SAVE SIGNATURE PNG TO DISK -------------------

$signature_saved = 0;
$signature_error = '';
$signature_path  = '';

if ($has_signature && $signature_b64 !== '') {
    $sigData = base64_decode($signature_b64);
    if ($sigData !== false && strlen($sigData) > 0) {

        // This is the directory we EXPECT to see over FTP
        $sigDir = dirname(__FILE__) . '/fuel_signatures';

        if (!is_dir($sigDir)) {
            if (!mkdir($sigDir, 0775, true)) {
                $signature_error = 'mkdir failed for ' . $sigDir;
                error_log($signature_error);
            }
        }

        if ($signature_error === '') {
            $sigFile = $sigDir . '/signature_' . intval($sessionId) . '.png';
            $signature_path = $sigFile;

            if (file_put_contents($sigFile, $sigData) === false) {
                $signature_error = 'file_put_contents failed for ' . $sigFile;
                error_log($signature_error);
            } else {
                $signature_saved = 1;
            }
        }
    } else {
        $signature_error = 'base64_decode failed or empty data';
        error_log($signature_error);
    }
}

// (Optional) TODO: handle $image_b64 similarly if/when you use photos.

// ---- SUCCESS ------------------------------------------------

json_exit(array(
    'ok'              => 1,
    'id'              => $sessionId,
    'has_signature'   => $has_signature,
    'signature_saved' => $signature_saved,
    'signature_path'  => $signature_path,
    'signature_error' => $signature_error
));
?>