<?php

require_once('simple_html_dom.php');
require_once('config.php');
$url = 'https://www.dkb.de/';

require_once "class.crawler.php";



/*function doCurlPost($action, $data) {
	global $url, $ch;
	
	$lastUri = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	if ($lastUri) { curl_setopt($ch, CURLOPT_REFERER, $lastUri); }

	curl_setopt($ch, CURLOPT_URL, $url . $action);
	curl_setopt($ch, CURLOPT_POST, count($data));
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	
	return curl_exec($ch);
}

function doCurlGet($path) {
	global $url, $ch;
	
	$lastUri = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	if ($lastUri) { curl_setopt($ch, CURLOPT_REFERER, $lastUri); }

	curl_setopt($ch, CURLOPT_URL, $path);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	
	return curl_exec($ch);
}

//
// CURL init
//
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIESESSION, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'data/cookie.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, 'data/cookie.txt');
//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
//curl_setopt($ch, CURLOPT_CAINFO, 'cacert.pem');

//
// LOGIN
//
echo 'Logging in...';
$result = doCurlGet($url.'banking');

$dom = str_get_html($result);
$form = $dom->find('form', 1);

$post_data = array();
foreach ($form->find('input') as $elem) {	
	if ($elem->name == 'j_username') $elem->value = $kto;
	if ($elem->name == 'j_password') $elem->value = $pin;
	
	$post_data[$elem->name] = $elem->value;	
}
$html_ = doCurlPost('banking', $post_data);

if (strpos($html_, 'Letzte Anmeldung:') !== false) {
	echo "OK!\n";
} else {
	echo 'Error. Login failed!';
	die();
}

//
// get Konten
//
echo "get Konten...\n";
$accounts = array();
$matches = array();

$dom_ = str_get_html($html_);
$cnt = 0;
foreach ($dom_->find('table[class=financialStatusTable] tr') as $k => $row) {
	if ($row->class != 'mainRow') { continue; }
	
	// loop
	$td = $row->find('td', 0);
	if (!$td) continue;

	$desc = trim(strip_tags($td->find('div', 0)->plaintext));
	$nr = trim($td->find('div', 1)->plaintext);
	$nr = str_replace('*', '_', $nr);
	$ec = strpos($td->find('div', 1)->plaintext, 'DE') !== false;

	if ($desc == 'Depot') break;
	
	echo "  found '$desc' ($nr)";
	echo $ec ? " - is EC" :  " - is CC";
	echo " - load Details";
	$html = doCurlGet($url . 'DkbTransactionBanking/content/banking/financialstatus/FinancialComposite/FinancialStatus.xhtml?$event=paymentTransaction&row='.$cnt.'&group=0');

	// download CSV
	echo " - download CSV";
	$ums = $ec ? 'kontoumsaetze' : 'kreditkartenumsaetze';
	$csv = doCurlGet($url . 'banking/finanzstatus/'.$ums.'?$event=csvExport');
	file_put_contents("$desc-$cnt-$nr-$ec.csv",$csv);
	$row->clear(); 
	unset($row);

	$cnt++;
	
	echo "\n";
	$accounts[$nr] = ['desc' => $desc, 'csv' => $csv, 'nr' => $nr, 'type' => $ec?'ec':'cc'];
}

//
// Logout
//
echo "Logout!\n";
$html = doCurlGet($url . '/DkbTransactionBanking/banner.xhtml?$event=logout');*/

$crawler = new crawler();

$crawler->getDataToCSV();
