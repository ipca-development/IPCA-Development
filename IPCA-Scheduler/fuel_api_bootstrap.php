<?php
// fuel_api_bootstrap.php
// Returns active users + US devices for the Fuel Station iOS app.

// ===== CONFIG ==========================================================
$db_host = "mysql056.hosting.combell.com";
$db_user = "ID127947_egl1";          
$db_pass = "Plane123";       
$db_name = "ID127947_egl1";

$api_key_config = "F9K7T2R8M4Q1B6X0H3W2C8N5Y4D9P"; // same key you will put in the iOS app

// ----- HELPERS ---------------------------------------------------------
function send_json($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

// ----- AUTH ------------------------------------------------------------
$api_key = isset($_GET['api_key']) ? $_GET['api_key'] :
           (isset($_POST['api_key']) ? $_POST['api_key'] : '');

if ($api_key !== $api_key_config) {
    send_json(array("ok" => 0, "error" => "unauthorized"));
}

// ----- DB CONNECT ------------------------------------------------------
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    send_json(array("ok" => 0, "error" => "db_connect_failed"));
}
$mysqli->set_charset("utf8");

// ----- USERS: only active ---------------------------------------------
// Active if:
//  - actief_tot = '0000-00-00'  (no expiry / legacy)
//  OR
//  - actief_tot >= today
//
// We return:
//
//  userid         -> id
//  naam           -> last_name
//  voornaam       -> first_name
//  actief_tot     -> active_until (YYYY-MM-DD)
//  gebruikersnaam -> username

$sql_users = "
    SELECT
        userid,
        naam,
        voornaam,
        actief_tot,
        gebruikersnaam
    FROM users
    WHERE
        (actief_tot = '0000-00-00' OR actief_tot >= CURDATE())
    ORDER BY naam, voornaam
";

$res_users = $mysqli->query($sql_users);
$users = array();

if ($res_users) {
    while ($row = $res_users->fetch_assoc()) {
        $users[] = array(
            "id"           => intval($row["userid"]),
            "last_name"    => $row["naam"],
            "first_name"   => $row["voornaam"],
            "active_until" => $row["actief_tot"],
            "username"     => $row["gebruikersnaam"]
            // NOTE: we intentionally do NOT send 'paswoord' here for security.
        );
    }
    $res_users->free();
}

// ----- DEVICES: Alpha Pro aircraft in the US --------------------------
// Requirements you specified:
//  dev_id
//  dev_name (tail number)
//  dev_active = 'YES'
//  dev_vis = 'Y'
//  dev_type = 'AIRCRAFT'
//  dev_sort = 'Alpha Pro'

$sql_devices = "
    SELECT
        dev_id,
        dev_name,
        dev_type,
        dev_sort,
        dev_active,
        dev_vis
    FROM devices
    WHERE
        dev_type = 'AIRCRAFT'
        AND dev_sort = 'Alpha Pro'
        AND dev_active = 'YES'
        AND dev_vis = 'Y'
    ORDER BY dev_name
";

$res_devices = $mysqli->query($sql_devices);
$devices = array();

if ($res_devices) {
    while ($row = $res_devices->fetch_assoc()) {
        $devices[] = array(
            "id"          => intval($row["dev_id"]),
            "tail_number" => $row["dev_name"]
        );
    }
    $res_devices->free();
}

$mysqli->close();

send_json(array(
    "ok"      => 1,
    "users"   => $users,
    "devices" => $devices
));
?>