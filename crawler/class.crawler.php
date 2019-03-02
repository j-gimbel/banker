<?php
error_reporting(-1);
date_default_timezone_set("Europe/Berlin");
require_once('simple_html_dom.php');
require('config.php');
$url = 'https://www.dkb.de/';

require("../parser/class.parser.php");

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
  public $debug = True;
  public $log;
  public data;
  
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
  
  
  
  public function doCrawl() {
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
      
      
      $p = new parser;
      $p->makeLookupStructure();
      
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
        
        foreach (array (0,2,4,5,9,11,13,15,17,19)   as $index ) {
          
          $foundKey = 0;
          foreach (array("Kontonummer","Buchungstag","Wertstellung","Betrag","Auftraggeber / Begünstigter","IBAN / BIC","Buchungstext","Verwendungszweck","Kundenreferenz","Als Überweisung verwenden") as $key ) {
          
            if (preg_match("/".$key."/",$spans[$index]) === 1) {
              $foundKey = 1;
            }
          }
          
          if ($foundKey == 0) {
            
            print ("Did not find $spans[$index] in buchung. Stopping...");
            exit;
            
          }
          
        }
        $buchung = array();
        $buchung["buchungsTag"] = date("Y-m-d", strtotime($spans[3]) );
        $buchung["wertstellungstag"] = date("Y-m-d", strtotime($spans[5]) );
        #$buchung["buchungsartID"] = 
        $buchung["buchungsart"] = $p->filterBuchungsArtName($spans[14]);
        if ( isset($this->lookup['buchungsArten'][$buchung["buchungsart"]]) ) {
          $buchung["buchungsartID"] = $this->lookup['buchungsArten'][$buchung["buchungsart"]]['buchungsartID'];
        } else { 
          $buchung["buchungsartID"] = $this->addBuchungsArt($buchung["buchungsart"],"found first time in file '".$this->currentFile["name"]."'");
        }
        
            
        #$buchung["buchungsGruppenIDs"] = 
        $buchung["changeValue"] = $spans[8];
        #$buchung["absValue"] = 
        $buchung["buchungstext"] = $spans[10]." ".$spans[16];
        $buchung["mandatName"] = $this->filterMandatName($spans[10]);
        $buchung["mandatID"] = 0;
        foreach ($this->lookup['mandate'] as $possibleMandatID => $dummy) {
          $mandatName = $this->lookup['mandate'][$possibleMandatID]['mandatName'];
            if ($mandatName == $buchung["mandatName"]) {
              if ($CRED == $buchung["sepa"]["CRED"])  {
                    $buchung["mandatID"] = $possibleMandatID;
                  break;
              }
          }
        }
        if ($buchung["mandatID"] == 0) {
        // no mandatID found, add as new
          $buchung["mandatID"] = $p->addMandat($buchung["mandatName"],$buchung["sepa"]["CRED"],$buchung["sepa"]["MREF"]);
        }
        $data["kontoauszugdateiID"] = 0;
        
        $buchung["sepaJSON"] = array();
        $buchung["sepaJSON"]["KREF"] = $spans[18];
        
        $buchung["sepaJSON"]["IBAN"] = preg_split('/\s*\/\s*',$spans[12])[0];
        $buchung["sepaJSON"]["BIC"] = preg_split('/\s*\/\s*',$spans[12])[2];

        
        
        
        
        
        
        
        
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