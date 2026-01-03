<?php
// backend-php/src/Infrastructure/Templates/bcaa_rca_cap.php

$audit         = $data['audit'] ?? [];
$findingBlocks = $data['findingBlocks'] ?? [];
$revision      = $data['revision'] ?? '1';
$reportDate    = $data['reportDate'] ?? date('d/m/Y');
$auditDateLabel= $data['auditDateLabel'] ?? '';
$organizationName = $data['organizationName'] ?? 'EuroPilot Center';

// Audit fields
$ref        = $audit['external_ref'] ?? '';
$auditType  = $audit['audit_type'] ?? '';
$auditStatus= $audit['status'] ?? '';
$startDate  = $audit['start_date'] ?? '';
$endDate    = $audit['end_date'] ?? '';
$closedDate = $audit['closed_date'] ?? '/';
$subject    = $audit['subject'] ?? '/';

// Optional auditors/attendees fields (if you add them later to DB)
$auditors   = $audit['auditors'] ?? '/';
$attendees  = $audit['attendees'] ?? '/';

// ---------- Logo as DATA URI (most reliable with Dompdf)
$logoPath = __DIR__ . '/../../../../public/assets/epc-logo.png'; // adjust if needed
$logoSrc = null;
if (file_exists($logoPath)) {
    $imgData = file_get_contents($logoPath);
    $logoSrc = 'data:image/png;base64,' . base64_encode($imgData);
}
$logoDebug = 'LOGO PATH: ' . $logoPath . ' | exists=' . (file_exists($logoPath) ? 'YES' : 'NO') . ' | size=' . (file_exists($logoPath) ? filesize($logoPath) : 0);

// ---------- Helpers
function esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function blockHeader($title, $color) {
    return '<div style="background:' . $color . ';color:#fff;font-weight:bold;padding:10px 12px;font-size:14px;">' . esc($title) . '</div>';
}

function cellBlock($label, $value, $bg) {
    return '
      <td style="background:' . $bg . ';border:1px solid #c8c8c8;padding:10px;vertical-align:top;">
        <div style="font-weight:bold;color:#000;margin-bottom:6px;">' . esc($label) . '</div>
        <div style="color:#000;">' . nl2br(esc($value)) . '</div>
      </td>';
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 18mm 12mm 16mm 12mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color:#000; }

  .pagebreak { page-break-before: always; }

  .headerTable { width:100%; border-collapse: collapse; margin-bottom: 14px; }
  .headerTable td { border:2px solid #000; vertical-align: middle; }
  .hLeft  { width:32%; height:70px; text-align:center; }
  .hMid   { width:46%; height:70px; text-align:center; font-weight:bold; font-size:18px; }
  .hRight { width:22%; height:70px; padding:10px; font-size:14px; font-weight:bold; }

  .titleBox { text-align:center; margin: 8px 0 12px 0; }
  .titleBox .t1 { font-size:20px; font-weight:bold; }
  .titleBox .t2 { font-size:14px; font-weight:bold; margin-top:4px; }

  table.grid { width:100%; border-collapse: collapse; margin-top:0; table-layout:fixed; }
  table.grid td { border:1px solid #c8c8c8; }

  .sectionSpacer { height:10px; }

  .findingsTable { width:100%; border-collapse: collapse; margin-top:10px; table-layout:fixed; }
  .findingsTable th, .findingsTable td { border:1px solid #c8c8c8; padding:8px; font-size:11px; vertical-align:top; }
  .findingsTable th { background:#efefef; font-weight:bold; }

  .smallNote { font-size:10px; color:#444; margin-top:10px; }
</style>
</head>
<body>

<?php
// =====================================================
// PAGE 1: Header + Title + Details + Audit Team & Attendance
// =====================================================
?>
<table class="headerTable">
  <tr>
    <td class="hLeft">
      <?php if ($logoSrc): ?>
        <img src="<?php echo esc($logoSrc); ?>" style="max-height:60px; max-width:95%; margin-top:6px;">
      <?php else: ?>
        <div style="font-size:12px; font-weight:bold;">(Logo)</div>
		<div style="font-size:9px;color:#666;"><?php echo esc($logoDebug); ?></div>
      <?php endif; ?>
    </td>
    <td class="hMid">
      Root Cause Analysis (RCA) and Corrective Action Plan (CAP) – Audit <?php echo esc($ref); ?>
    </td>
    <td class="hRight">
      <div>Page: 1</div>
      <div style="margin-top:6px;">Date: <?php echo esc($reportDate); ?></div>
      <div style="margin-top:6px;">Revision: <?php echo esc($revision); ?></div>
    </td>
  </tr>
</table>

<div class="titleBox">
  <div class="t1">Root Cause Analysis &amp; Corrective Action Plan</div>
  <div class="t2">Audit: <?php echo esc($ref); ?><?php echo $auditDateLabel ? ' — Date: ' . esc($auditDateLabel) : ''; ?></div>
</div>

<?php echo blockHeader('Details', '#1f3d5a'); ?>

<table class="grid">
  <tr>
    <?php
      echo cellBlock('Reference:', $ref, '#efefef');
      echo cellBlock('Organization:', $organizationName, '#efefef');
    ?>
  </tr>

  <tr>
    <?php
      echo cellBlock('Status:', $auditStatus ?: '/', '#ffffff');
      echo cellBlock('Audit Type:', $auditType ?: '/', '#ffffff');
    ?>
  </tr>

  <tr>
    <td colspan="2" style="background:#efefef;border:1px solid #c8c8c8;padding:10px;vertical-align:top;">
      <div style="font-weight:bold;color:#000;margin-bottom:6px;">Subject / Scope:</div>
      <div style="color:#000;"><?php echo nl2br(esc($subject ?: '/')); ?></div>
    </td>
  </tr>

  <tr>
    <?php
      echo cellBlock('Start Date:', $startDate ?: '/', '#ffffff');
      echo cellBlock('End Date:', $endDate ?: '/', '#ffffff');
      echo cellBlock('Closed Date:', $closedDate ?: '/', '#ffffff');
    ?>
  </tr>
</table>

<div class="sectionSpacer"></div>

<?php echo blockHeader('Audit Team & Attendance', '#1f3d5a'); ?>
<table class="grid">
  <tr>
    <td style="width:50%;background:#efefef;border:1px solid #c8c8c8;padding:10px;vertical-align:top;">
      <div style="font-weight:bold;color:#000;margin-bottom:6px;">Auditors:</div>
      <div style="color:#000;"><?php echo nl2br(esc($auditors ?: '/')); ?></div>
    </td>
    <td style="width:50%;background:#efefef;border:1px solid #c8c8c8;padding:10px;vertical-align:top;">
      <div style="font-weight:bold;color:#000;margin-bottom:6px;">Attendees:</div>
      <div style="color:#000;"><?php echo nl2br(esc($attendees ?: '/')); ?></div>
    </td>
  </tr>
</table>

<div class="smallNote">
  NOTE: This report is generated by IPCA.aero. Verify dates/status before submission.
</div>

<?php
// =====================================================
// PAGE 2: List of Findings + Status
// =====================================================
?>
<div class="pagebreak"></div>

<table class="headerTable">
  <tr>
    <td class="hLeft">
      <?php if ($logoSrc): ?>
        <img src="<?php echo esc($logoSrc); ?>" style="max-height:60px; max-width:95%; margin-top:6px;">
      <?php else: ?>
        <div style="font-size:12px; font-weight:bold;">(Logo)</div>
      <?php endif; ?>
    </td>
    <td class="hMid">
      Root Cause Analysis (RCA) and Corrective Action Plan (CAP) – Audit <?php echo esc($ref); ?>
    </td>
    <td class="hRight">
      <div>Page: 2</div>
      <div style="margin-top:6px;">Date: <?php echo esc($reportDate); ?></div>
      <div style="margin-top:6px;">Revision: <?php echo esc($revision); ?></div>
    </td>
  </tr>
</table>

<?php echo blockHeader('List of Findings + Status', '#1f3d5a'); ?>

<table class="findingsTable">
  <thead>
    <tr>
      <th style="width:7%;">#</th>
      <th style="width:20%;">Finding Reference</th>
      <th>Title</th>
      <th style="width:12%;">Classification</th>
      <th style="width:16%;">Status</th>
      <th style="width:12%;">Target Date</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($findingBlocks as $fb): $f = $fb['finding']; ?>
      <tr>
        <td><?php echo esc($fb['idx']); ?></td>
        <td><?php echo esc($f['reference'] ?? ''); ?></td>
        <td><?php echo esc($f['title'] ?? ''); ?></td>
        <td><?php echo esc(str_replace('_',' ', $f['classification'] ?? '')); ?></td>
        <td><?php echo esc(str_replace('_',' ', $f['status'] ?? '')); ?></td>
        <td><?php echo esc($f['target_date'] ?? '/'); ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php
// =====================================================
// Per finding: RCA (orange) page + CAP (green) page
// =====================================================
$pageNum = 3;

foreach ($findingBlocks as $fb):
  $f = $fb['finding'] ?? [];
  $idx = $fb['idx'] ?? '';
  $findingRef = $f['reference'] ?? '';
  $findingTitle = $f['title'] ?? '';
  $rcaSteps = $fb['rcaSteps'] ?? [];
  $actions = $fb['actions'] ?? [];

  // Sort RCA steps by whyNumber
  if (is_array($rcaSteps)) {
    usort($rcaSteps, function($a,$b){
      return (int)($a['whyNumber'] ?? 0) <=> (int)($b['whyNumber'] ?? 0);
    });
  }
?>

<!-- RCA PAGE -->
<div class="pagebreak"></div>
<table class="headerTable">
  <tr>
    <td class="hLeft"><?php if ($logoSrc): ?><img src="<?php echo esc($logoSrc); ?>" style="max-height:60px; max-width:95%; margin-top:6px;"><?php else: ?><div style="font-size:12px; font-weight:bold;">(Logo)</div><?php endif; ?></td>
    <td class="hMid">Audit <?php echo esc($ref); ?> — Finding <?php echo esc($idx); ?></td>
    <td class="hRight"><div>Page: <?php echo esc($pageNum); ?></div><div style="margin-top:6px;">Date: <?php echo esc($reportDate); ?></div><div style="margin-top:6px;">Revision: <?php echo esc($revision); ?></div></td>
  </tr>
</table>
<?php $pageNum++; ?>

<?php echo blockHeader('ROOT CAUSE ANALYSIS - FINDING '.$idx.' - '.$findingRef, '#d17b00'); ?>

<table class="grid">
  <tr>
    <?php echo cellBlock('Finding Reference:', $findingRef, '#efefef'); ?>
    <?php echo cellBlock('Finding Title:', $findingTitle, '#efefef'); ?>
  </tr>
  <tr>
    <?php echo cellBlock('Classification:', str_replace('_',' ', $f['classification'] ?? ''), '#ffffff'); ?>
    <?php echo cellBlock('Regulation Ref:', $f['regulation_ref'] ?? '/', '#ffffff'); ?>
  </tr>
  <tr>
    <?php echo cellBlock('Target Date:', $f['target_date'] ?? '/', '#efefef'); ?>
    <?php echo cellBlock('Finding Status:', str_replace('_',' ', $f['status'] ?? ''), '#efefef'); ?>
  </tr>
  <tr>
    <td colspan="2" style="background:#ffffff;border:1px solid #c8c8c8;padding:10px;vertical-align:top;">
      <div style="font-weight:bold;color:#000;margin-bottom:6px;">Finding Statement / Description:</div>
      <div style="color:#000;"><?php echo nl2br(esc($f['description'] ?? '/')); ?></div>
    </td>
  </tr>
</table>

<div class="sectionSpacer"></div>

<?php echo blockHeader('5 Whys', '#1f3d5a'); ?>
<table class="findingsTable">
  <thead>
    <tr>
      <th style="width:8%;">Why</th>
      <th style="width:42%;">Question</th>
      <th>Answer</th>
    </tr>
  </thead>
  <tbody>
    <?php if (is_array($rcaSteps)): ?>
      <?php foreach ($rcaSteps as $s):
        $n = (int)($s['whyNumber'] ?? 0);
        if ($n < 1 || $n > 5) continue;
      ?>
        <tr>
          <td><?php echo esc($n); ?></td>
          <td><?php echo esc($s['question'] ?? ''); ?></td>
          <td><?php echo esc($s['answer'] ?? ''); ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<!-- CAP PAGE -->
<div class="pagebreak"></div>
<table class="headerTable">
  <tr>
    <td class="hLeft"><?php if ($logoSrc): ?><img src="<?php echo esc($logoSrc); ?>" style="max-height:60px; max-width:95%; margin-top:6px;"><?php else: ?><div style="font-size:12px; font-weight:bold;">(Logo)</div><?php endif; ?></td>
    <td class="hMid">Audit <?php echo esc($ref); ?> — Finding <?php echo esc($idx); ?></td>
    <td class="hRight"><div>Page: <?php echo esc($pageNum); ?></div><div style="margin-top:6px;">Date: <?php echo esc($reportDate); ?></div><div style="margin-top:6px;">Revision: <?php echo esc($revision); ?></div></td>
  </tr>
</table>
<?php $pageNum++; ?>

<?php echo blockHeader('CORRECTIVE ACTION PLAN - FINDING '.$idx.' - '.$findingRef, '#1f8f3a'); ?>

<!-- Fixed (non-crooked) approval row -->
<table class="grid" style="table-layout:fixed;">
  <tr>
    <td style="width:50%;background:#efefef;border:1px solid #c8c8c8;padding:10px;vertical-align:top;">
      <div style="font-weight:bold;color:#000;margin-bottom:6px;">Proposed Action Approved by:</div>
      <div style="color:#000;">__________________________</div>
    </td>
    <td style="width:50%;background:#efefef;border:1px solid #c8c8c8;padding:10px;vertical-align:top;">
      <div style="font-weight:bold;color:#000;margin-bottom:6px;">Date of RCA/CAP:</div>
      <div style="color:#000;"><?php echo esc($reportDate); ?></div>
    </td>
  </tr>
</table>

<div class="sectionSpacer"></div>

<table class="findingsTable">
  <thead>
    <tr>
      <th style="width:8%;">#</th>
      <th style="width:14%;">Type</th>
      <th>Description</th>
      <th style="width:14%;">Due Date</th>
      <th style="width:18%;">Effectiveness</th>
    </tr>
  </thead>
  <tbody>
    <?php
      $ai = 1;
      if (is_array($actions)):
        foreach ($actions as $a):
    ?>
      <tr>
        <td><?php echo esc($ai); ?></td>
        <td><?php echo esc($a['action_type'] ?? ''); ?></td>
        <td><?php echo esc($a['description'] ?? ''); ?></td>
        <td><?php echo esc($a['due_date'] ?? '/'); ?></td>
        <td><?php echo esc(str_replace('_',' ', $a['effectiveness'] ?? 'NOT_EVALUATED')); ?></td>
      </tr>
    <?php
        $ai++;
        endforeach;
      endif;
    ?>
  </tbody>
</table>

<?php endforeach; ?>

</body>
</html>