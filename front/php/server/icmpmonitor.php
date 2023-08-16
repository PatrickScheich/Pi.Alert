<?php
session_start();

if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}

//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  icmpmonitor.php - Front module. Server side. Manage Devices
//------------------------------------------------------------------------------
//  leiweibau  2023        https://github.com/leiweibau     GNU GPLv3
//------------------------------------------------------------------------------

foreach (glob("../../../db/setting_language*") as $filename) {
	$pia_lang_selected = str_replace('setting_language_', '', basename($filename));
}
if (strlen($pia_lang_selected) == 0) {$pia_lang_selected = 'en_us';}

//------------------------------------------------------------------------------
// External files
require 'db.php';
require 'util.php';
require 'journal.php';
require '../templates/language/' . $pia_lang_selected . '.php';

//------------------------------------------------------------------------------
//  Action selector
//------------------------------------------------------------------------------
// Set maximum execution time to 1 minute
ini_set('max_execution_time', '60');

// Open DB
OpenDB();

// Action functions
if (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) {
	$action = $_REQUEST['action'];
	switch ($action) {
	case 'setServiceData':setServiceData();
		break;
	case 'deleteService':deleteService();
		break;
	case 'insertNewICMPHost':insertNewICMPHost();
		break;
	case 'EnableICMPMon':EnableICMPMon();
		break;
	case 'getDevicesList':getDevicesList();
		break;
	case 'getICMPHostTotals':getICMPHostTotals();
		break;
	}
}

function getICMPHostTotals() {
	global $db;

	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_PresentLastScan=0 AND icmp_AlertDown=1";
	$alertDown_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_PresentLastScan=1";
	$online_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_Favorite=1";
	$favorite_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon";
	$all_Count = $db->querySingle($query);

	$totals = array($all_Count, $alertDown_Count, $online_Count, $favorite_Count);
	echo (json_encode($totals));
}

function getDevicesList() {
	global $db;

	// SQL
	//$condition = getDeviceCondition($_REQUEST['status']);
	$sql = 'SELECT * FROM ICMP_Mon';
	$result = $db->query($sql);
	// arrays of rows
	$tableData = array();
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		$tableData['data'][] = array(
			$row['icmp_ip'],
			$row['icmp_hostname'],
			$row['icmp_LastScan'],
			$row['icmp_avgrtt'],
			$row['icmp_PresentLastScan'],
			$row['icmp_AlertDown'],
			//$row['rowid'], // Rowid (hidden)
		);
	}
	// Control no rows
	if (empty($tableData['data'])) {
		$tableData['data'] = '';
	}
	// Return json
	echo (json_encode($tableData));
}

//------------------------------------------------------------------------------
//  Set Services Data
//------------------------------------------------------------------------------
// function setServiceData() {
// 	global $db;
// 	global $pia_lang;
// 	// sql
// 	$sql = 'UPDATE Services SET
//                  mon_Tags           = "' . quotes($_REQUEST['tags']) . '",
//                  mon_MAC            = "' . quotes($_REQUEST['mac']) . '",
//                  mon_AlertDown      = "' . quotes($_REQUEST['alertdown']) . '",
//                  mon_AlertEvents    = "' . quotes($_REQUEST['alertevents']) . '"
//           WHERE mon_URL="' . $_REQUEST['url'] . '"';
// 	// update Data
// 	$result = $db->query($sql);
// 	// check result
// 	if ($result == TRUE) {
// 		// Logging
// 		pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0002', '', $_REQUEST['url']);
// 		echo $pia_lang['BackWebServices_UpdServ'];
// 	} else {
// 		// Logging
// 		pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0004', '', $_REQUEST['url']);
// 		echo $pia_lang['BackWebServices_UpdServError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
// 		//echo $_REQUEST['tags'];
// 	}
// }

//------------------------------------------------------------------------------
//  Delete Service
//------------------------------------------------------------------------------
// function deleteService() {
// 	global $db;
// 	global $pia_lang;

// 	$url = $_REQUEST['url'];
// 	if (!$url || !is_string($url) || !preg_match('/^http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(\/.*)?$/i', $url)) {
// 		return false;
// 	}

// 	// sql
// 	$sql = 'DELETE FROM Services WHERE mon_URL="' . $_REQUEST['url'] . '"';
// 	// execute sql
// 	$result = $db->query($sql);
// 	// Remove Events too
// 	$sql = 'DELETE FROM Services_Events WHERE moneve_URL="' . $_REQUEST['url'] . '"';
// 	// execute sql
// 	$result = $db->query($sql);
// 	// check result
// 	if ($result == TRUE) {
// 		// Logging
// 		pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0003', '', $url);
// 		echo $pia_lang['BackWebServices_DelServ'];
// 		echo ("<meta http-equiv='refresh' content='2; URL=./services.php'>");
// 	} else {
// 		// Logging
// 		pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0005', '', $url);
// 		echo $pia_lang['BackWebServices_DelServError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
// 	}
// }

//------------------------------------------------------------------------------
//  Insert Service
//------------------------------------------------------------------------------
function insertNewICMPHost() {
	global $db;
	global $pia_lang;

	//echo 'Enter Function';

	$hostip = $_REQUEST['icmp_ip'];

	if (!filter_var($hostip, FILTER_FLAG_IPV4) && !filter_var($hostip, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
		echo $pia_lang['BackICMP_mon_InsICMPError'];
		return false;
	}

	$check_timestamp = date("Y-m-d H:i:s");

	// sql
	$sql = 'INSERT INTO ICMP_Mon ("icmp_ip", "icmp_hostname", "icmp_LastScan", "icmp_PresentLastScan", "icmp_avgrtt", "icmp_AlertEvents", "icmp_AlertDown", "icmp_Favorite")
                         VALUES("' . $hostip . '", "' . $_REQUEST['icmp_hostname'] . '", "' . $check_timestamp . '", "0", "99999", "' . $_REQUEST['alertevents'] . '", "' . $_REQUEST['alertdown'] . '", "' . $_REQUEST['icmp_fav'] . '")';

	// execute sql
	$result = $db->query($sql);
	// check result
	if ($result == TRUE) {
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0001', '', $hostip);
		echo $pia_lang['BackICMP_mon_InsICMP'];
		echo ("<meta http-equiv='refresh' content='2; URL=./icmpmonitor.php'>");
	} else {
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0001', '', $hostip);
		echo $pia_lang['BackICMP_mon_InsICMPError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
	}

}

//------------------------------------------------------------------------------
//  Toggle Web Service Monitoring
//------------------------------------------------------------------------------
function EnableICMPMon() {
	global $pia_lang;

	if ($_SESSION['ICMPScan'] == True) {
		exec('../../../back/pialert-cli disable_icmp_mon', $output);
		echo $pia_lang['BackICMP_mon_disabled'];
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0304', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
	} else {
		exec('../../../back/pialert-cli enable_icmp_mon', $output);
		echo $pia_lang['BackICMP_mon_enabled'];
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0303', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
	}
}

?>
