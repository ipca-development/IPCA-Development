<?php
/************************************************************
 * fuel_api_login.php — PHP 5.3 compatible
 * Login endpoint for IPCA Fuel Station Kiosk
 * Expects: POST api_key, username, password
 * Returns: JSON
 ************************************************************/

header('Content-Type: application/json; charset=utf-8');

// ---- CONFIG -------------------------------------------------

$db_host = "mysql056.hosting.combell.com";
$db_user = "ID127947_egl1";      // your DB user
$db_pass = "Plane123";           // your DB password
$db_name = "ID127947_egl1";      // your DB name

$API_KEY = 'F9K7T2R8M4Q1B6X0H3W2C8N5Y4D9P';

// ---- HELPER: JSON RESPONSE ----------------------------------

function json_exit($arr) {
    echo json_encode($arr);
    exit;
}

// ---- VALIDATE POST ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(array('ok'=>0,'error'=>'POST required'));
}

$api_key  = isset($_POST['api_key'])   ? trim($_POST['api_key'])   : '';
$username = isset($_POST['username'])  ? trim($_POST['username'])  : '';
$password = isset($_POST['password'])  ? (string)$_POST['password'] : '';

if ($api_key=='' || $username=='' || $password=='') {
    json_exit(array('ok'=>0,'error'=>'Missing credentials'));
}

if ($api_key !== $API_KEY) {
    json_exit(array('ok'=>0,'error'=>'Invalid API key'));
}

// ---- CONNECT -------------------------------------------------

// IMPORTANT: use $db_host, $db_user, $db_pass, $db_name (lowercase)
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_errno) {
    json_exit(array(
        'ok'    => 0,
        'error' => 'DB connection failed to host '.$db_host.': '.$mysqli->connect_error
    ));
}

$mysqli->set_charset('utf8');

// ---- PREPARE QUERY ------------------------------------------

$sql = "
    SELECT 
        userid,
        naam,
        voornaam,
        actief_tot,
        gebruikersnaam
    FROM users
    WHERE 
        gebruikersnaam = ?
        AND paswoord = ?
        AND (
            actief_tot = '0000-00-00'
            OR actief_tot >= CURDATE()
        )
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    json_exit(array('ok'=>0,'error'=>'Prepare failed: '.$mysqli->error));
}

$stmt->bind_param('ss', $username, $password);

if (!$stmt->execute()) {
    json_exit(array('ok'=>0,'error'=>'Execute failed'));
}

// ---- PHP 5.3: use bind_result + fetch ------------------------

$stmt->bind_result($userid, $naam, $voornaam, $actief_tot, $gebruikersnaam);

if ($stmt->fetch()) {

    $user = array(
        'id'           => (int)$userid,
        'last_name'    => (string)$naam,
        'first_name'   => (string)$voornaam,
        'active_until' => (string)$actief_tot,
        'username'     => (string)$gebruikersnaam
    );

    json_exit(array('ok'=>1,'user'=>$user));

} else {
    json_exit(array('ok'=>0,'error'=>'Invalid username or password'));
}

?>