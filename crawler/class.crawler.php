<?php
error_reporting(-1);
date_default_timezone_set("Europe/Berlin");
require_once('simple_html_dom.php');
require('config.php');
$url = 'https://www.dkb.de/';


function exception_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}
set_error_handler("exception_error_handler");


class wrongValuesSumException extends Exception {};

class crawler {
  
  public $url = 'https://www.dkb.de/';
  public function __construct() {
    
    #$this->url = url = 'https://www.dkb.de/';
    
  }
  
  private function doCurlPost($action, $data,$ch) {
    #global $url, $ch;
    
    $lastUri = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    if ($lastUri) { curl_setopt($ch, CURLOPT_REFERER, $lastUri); }

    curl_setopt($ch, CURLOPT_URL, $this->url . $action);
    curl_setopt($ch, CURLOPT_POST, count($data));
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    
    return curl_exec($ch);
  }

  private function doCurlGet($path,$ch) {
    #global $url, $ch;
    
    $lastUri = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    if ($lastUri) { curl_setopt($ch, CURLOPT_REFERER, $lastUri); }

    curl_setopt($ch, CURLOPT_URL, $path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    
    return curl_exec($ch);
  }
  
  
  
  public function getDataToCSV() {
    require('config.php');
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
    $result = $this->doCurlGet($this->url.'banking',$ch);

    $dom = str_get_html($result);
    $form = $dom->find('form', 1);

    $post_data = array();
    foreach ($form->find('input') as $elem) {	
      if ($elem->name == 'j_username') $elem->value = $kto;
      if ($elem->name == 'j_password') $elem->value = $pin;
      
      $post_data[$elem->name] = $elem->value;	
    }
    $html_ = $this->doCurlPost('banking', $post_data,$ch);

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
      $html = $this->doCurlGet($this->url . 'DkbTransactionBanking/content/banking/financialstatus/FinancialComposite/FinancialStatus.xhtml?$event=paymentTransaction&row='.$cnt.'&group=0',$ch);
      
      $idCounter = 0;
      while (True) {
        print ("Counter $idCounter\n");
        $html = $this->doCurlGet($this->url .'banking/finanzstatus/kontoumsaetze?$event=pick&id='.$idCounter,$ch);
        #print ("html:".$html);
        
        #file_put_contents("$idCounter.txt",$html);
        
        $detailsDom = str_get_html($html );
        $detailsForm = $detailsDom->find('form[id=form945114534_1]',0);
        if (!$detailsForm) break;
        
        $spans = array();
        
        foreach ($detailsForm->find('span') as $index => $span) {
          
          $text = trim($span->plaintext);
          #print ($text."\n");
          array_push($spans,$text);
        }
        #print_r($spans);
        file_put_contents("$idCounter.txt",print_r($spans,true));
        /*

        Array
        (
            [0] => Kontonummer
            [1] => DE11 1203 0000 1013 1527 13 / Girokonto
            [2] => Buchungstag
            [3] => 01.03.2019
            [4] => Wertstellung
            [5] => 01.03.2019
            [6] => Betrag
            [7] => -46,96  EUR
            [8] => -46,96
            [9] => Auftraggeber / Begünstigter
            [10] => DANKE, IHR LIDL//Bremen/DE
        DANKE, IHR LIDL
            [11] => IBAN / BIC
            [12] => DE61 3005 0000 0008 0001 19 / WELADEDDXXX
            [13] => Buchungstext
            [14] => KARTENZAHLUNG
            [15] => Verwendungszweck
            [16] => 2019-02-28T08:14 Debitk.3 2022-12
            [17] => Kundenreferenz
            [18] => 6010462707322828021908141603401903
            [19] => Als Überweisung verwenden
            [20] => Als Überweisung verwenden
            [21] => Als Überweisung verwenden
            [22] =>
        )
        
        */
        
        
        $idCounter += 1;
        
      }
      
      
      // download CSV
      echo " - download CSV";
      $ums = $ec ? 'kontoumsaetze' : 'kreditkartenumsaetze';
      $csv = $this->doCurlGet($this->url . 'banking/finanzstatus/'.$ums.'?$event=csvExport',$ch);
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
    $html = $this->doCurlGet($this->url . '/DkbTransactionBanking/banner.xhtml?$event=logout',$ch);
    
    
  }
  
  
}