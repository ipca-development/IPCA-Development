<?php
// includes/ai_scheduler/ai_models.php

function get_scenario($db, $scenarioId) {
    $stmt = $db->prepare("SELECT * FROM scenarios WHERE id=?");
    $stmt->bind_param('i', $scenarioId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function get_device($db, $deviceId) {
    $stmt = $db->prepare("SELECT * FROM devices WHERE id=?");
    $stmt->bind_param('i', $deviceId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function persist_reservation_scenario($db, $reservationId, $scenarioId) {
    $stmt = $db->prepare("UPDATE reservations SET scenario_id=? WHERE id=?");
    $stmt->bind_param('ii', $scenarioId, $reservationId);
    $stmt->execute();
    return ($db->affected_rows >= 0);
}