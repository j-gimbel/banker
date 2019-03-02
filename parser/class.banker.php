<?php
error_reporting(-1);
date_default_timezone_set("Europe/Berlin");

function exception_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}
set_error_handler("exception_error_handler");


class wrongValuesSumException extends Exception {};

class parser {
    public $db;
    public $tables;
    public $lookup = array();
    
    public $currentFile;
    
    public $debug = True;
    public $log;
    
    
    public function __construct() {
        #return true;
        
        $this->absValue = 0;
        
        $this->tables = array();
        
        $this->tables["kontoauszuege"] = "CREATE TABLE IF NOT EXISTS kontoauszuege (kontoauszugID INTEGER PRIMARY KEY, beginnDatum TEXT, endDatum TEXT, kontoStandAlt REAL, kontoStand REAL, filename TEXT,buchungsIDsArray TEXT)";
        
        $this->tables["buchungsArten"] = "CREATE TABLE IF NOT EXISTS buchungsArten (buchungsartID INTEGER PRIMARY KEY, name TEXT, umsatzIDs TEXT, info TEXT)"; //Überweisung, Lastschrift, Kartenzahlung, Basislastschrift
        
        #$this->tables["buchungsGruppen"] = "CREATE TABLE IF NOT EXISTS buchungsGruppen (buchungsGruppenID INTEGER PRIMARY KEY, name TEXT, buchungsArtRegex TEXT, mandatRegex TEXT, info TEXT)"; 
        
        $this->tables["erwarteteBuchungen"] = "CREATE TABLE IF NOT EXISTS erwarteteBuchungen (erwBuchungsID INTEGER PRIMARY KEY, name TEXT, serchRegEx TEXT, dateRule TEXT, expectedDayOfMonth TEXT,dateStart TEXT, dateEnd TEXT)"; 
        
        
        $this->tables["mandate"] = "CREATE TABLE IF NOT EXISTS mandate (mandatID INTEGER PRIMARY KEY, mandatName TEXT, CRED TEXT, MREF TEXT, umsatzIDsJsonArray TEXT)";
        
        #$this->tables["umsaetzeGiroKonto"] = "CREATE TABLE IF NOT EXISTS umsaetzeGiroKonto (umsatzID INTEGER PRIMARY KEY, buchungstag TEXT, wertstellungstag TEXT, buchungsartID INTEGER,buchungsGruppenIDs, changeValue REAL, absValue REAL, buchungstext TEXT, SVWZ TEXT,EREF TEXT, mandatID INTEGER, kontoauszugdateiID INTEGER)";
        
        $this->tables["umsaetzeGiroKonto"] = "CREATE TABLE IF NOT EXISTS umsaetzeGiroKonto (umsatzID INTEGER PRIMARY KEY, buchungstag TEXT, wertstellungstag TEXT, buchungsartID INTEGER,buchungsGruppenIDs, changeValue REAL, absValue REAL, buchungstext TEXT, sepaJSON TEXT, mandatID INTEGER, kontoauszugdateiID INTEGER)";
        
        
        
    }

    public function __call($name, $arguments) {
        if (!method_exists($this, $name)) {
            throw new Exception($name . ' is not an existing method in class dbConnection');
        }
        if (!property_exists($this, $name)) {
            throw new Exception($name . ' is not an existing property in class dbConnection');
        }
    }
    
    
    private function file_get_contents_utf8($fn) {
        $content = file_get_contents($fn);
        return mb_convert_encoding($content, 'UTF-8',
        mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
    } 
    
    public function registerLogger($logger) {
       $this->log = $logger;
        
    }
    
    
    public function connectToDB() {
        $this->db = new PDO('sqlite:../db/banker.sqlite');
        $this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                
        #"PRAGMA journal_mode=OFF" , "PRAGMA synchronous = OFF"
        
        $this->db->exec("PRAGMA journal_mode=OFF");
        $this->db->exec("PRAGMA synchronous=OFF");
        
    }
    
    public function createTable($tableNamesArray) {
        if (in_array("all",$tableNamesArray,true) == True) {
            $tableNamesArray = array_keys($this->tables);
        }

        foreach ($tableNamesArray as $tableName) {
            $sth = $this->db->prepare("DROP TABLE IF EXISTS $tableName");
            $sth->execute(); 
            $this->db->exec($this->tables[$tableName]);
        }
    }
    
    public function makeLookupStructure() {
        $this->lookup['kontoauszuege'] = array();
        $sth = $this->db->prepare("SELECT filename,kontoauszugID FROM kontoauszuege");
        $sth->execute();
        $data = $sth->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as $row) {
            $this->lookup['kontoauszuege'][$row['filename']] = $row['kontoauszugID'];
        }
        $this->lookup['buchungsArten'] = array();
        $sth = $this->db->prepare("SELECT buchungsartID,name,umsatzIDs,info FROM buchungsArten");
        $sth->execute();
        $data = $sth->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as $row) {
            $this->lookup['buchungsArten'][$row['name']] = array();  
            $this->lookup['buchungsArten'][$row['name']]['buchungsartID'] = $row['buchungsartID'];
            $this->lookup['buchungsArten'][$row['name']]['umsatzIDs'] = json_decode($row['umsatzIDs']);
            $this->lookup['buchungsArten'][$row['name']]['info'] = $row['info'];
            $this->lookup['buchungsArten'][$row['name']]['umsatzIDs'] = $row['umsatzIDs'];
        }
        
        
        $this->lookup['buchungsGruppen'] = array();
        $sth = $this->db->prepare("SELECT buchungsGruppenID,name,serchRegEx,info FROM buchungsGruppen");
        $sth->execute();
        $data = $sth->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as $row) {
            $this->lookup['buchungsGruppen'][$row['name']] = array();  
            $this->lookup['buchungsGruppen'][$row['name']]['buchungsartID'] = $row['buchungsartID'];
            $this->lookup['buchungsGruppen'][$row['name']]['serchRegEx'] = json_decode($row['serchRegEx'],true);
            $this->lookup['buchungsGruppen'][$row['name']]['info'] = $row['info'];
        }
        
        
        
        $this->lookup['mandate'] = array();
        $sth = $this->db->prepare("SELECT mandatID,mandatName,CRED,MREF,umsatzIDsJsonArray FROM mandate");
        $sth->execute();
        $data = $sth->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as $row) {
          $this->lookup['mandate'][$row['mandatID']] = array();
          $this->lookup['mandate'][$row['mandatID']]['mandatName'] = $row['mandatName'];
          $this->lookup['mandate'][$row['mandatID']]['MREF'] = $row['MREF'];
          $this->lookup['mandate'][$row['mandatID']]['CRED'] = $row['CRED'];
          $this->lookup['mandate'][$row['mandatID']]['umsatzIDsJsonArray'] = json_decode($row['umsatzIDsJsonArray'],true);
        }
 
        $this->log->info('done: init lookupStructure');
        #$this->log->debug('done: init lookupStructure',$this->lookup);
        
    }
    
    
    public function addDataFromFiles($files) {
        $this->makeLookupStructure();
        foreach ($files as $fileIndex=>$origfile) {
            $this->analyzeKontoAuszugFile($origfile);
        }

        // connect umsatzIDs
        foreach ($this->lookup['buchungsArten'] as $buchungsArt=>$dummy) {
            
            $buchungsartID = $this->lookup['buchungsArten'][$buchungsArt]['buchungsartID'];
            $umsatzIDs = json_encode(array_unique($this->lookup['buchungsArten'][$buchungsArt]['umsatzIDs']),JSON_NUMERIC_CHECK);
            
            $update = "UPDATE buchungsArten SET umsatzIDs = :umsatzIDs WHERE buchungsartID = :buchungsartID";
           
            try {
                $stmt = $this->db->prepare($update);
                $stmt->bindParam(':umsatzIDs', $umsatzIDs );
                $stmt->bindParam(':buchungsartID', $buchungsartID );
                $stmt->execute(); 
            } catch(PDOException $e) {
                // Print PDOException message
                echo "PDO exception in ".$e->getFile()." in ".$e->getLine().": ".$e->getMessage()."\n";
            }
        }
    }
    
    private function checkErwBuchung ($buchung,$erwBuchung) {
      
        $matches = array();
      
        foreach ($erwBuchung['serchRegEx'] as $key=>$regex) {
            
            # print ("$key=>$regex\n");
            # print_r ($buchung);
            
            if (isset ($buchung[$key]) ) {
                if (in_array($key,array('buchungstext'))) {
                    if (preg_match( $regex, $buchung[$key] ) === 1) {
                      #print ("match key $key $regex for ".$buchung[$key]."\n");
                      $matches[$key] = 1;
                    }else {
                      $matches[$key] = 'Did not match $key = $regex in '.$buchung["buchungstext"];
                    }
                }
                else if (in_array($key,array('changeValue'))) {
                  $values = explode("...",$erwBuchung['serchRegEx']['changeValue']);
                  $valueToCheck = $buchung['changeValue'];
                  if (count($values) >1) {
                     $lowerValue = floatval($values[0]);
                    $upperValue = floatval($values[1]);
                    if ( ($lowerValue < $valueToCheck) and ($upperValue >= $valueToCheck) ) {
                       #print ("match key $key $regex for ".$buchung[$key]."\n");
                       $matches[$key] = 1;
                    } else{
                      $matches[$key] = False;
                    }
                  } else {
                    $value = floatval($erwBuchung['serchRegEx']['changeValue']);
                    if (abs($value-$valueToCheck) < 0.01) {
                      #print ("match key $key $regex for ".$buchung["changeValue"]."\n");
                    } else {
                      $matches[$key] = 'Did not find changeValue = $value. Value found is '.$buchung["changeValue"];
                    }
                  }
                }
                else {
                    $matches[$key] = 'Unknown search key $key';
                }
            }else {
                $matches[$key] = 'Did not find search key $key in Buchung array';
            }
        }
        
        $errortext = "";
        
        foreach ($matches as $key=> $text) {
          if ($text !== 1) {
             $errortext .= $text;
          }
        }
        if ($errortext !== "") {
          return $errortext;
        
        } else {
          
          return 1;
        }
        

    }
    
    public function checkErwBuchungenInMonth ($startDate,$endDate) {
      
      $startDay = date("d",$startDate);
      $endDay = date("d",$startDate);
      
      $sth = $this->db->prepare("SELECT erwBuchungsID, name,serchRegEx,dateRule,expectedDayOfMonth,dateStart,dateEnd FROM erwarteteBuchungen");
      $sth->execute();
      $data = $sth->fetchAll(PDO::FETCH_ASSOC);
      foreach ($data as $row) {
         
          $this->lookup['erwarteteBuchungen'][$row['name']] = array();  
          $this->lookup['erwarteteBuchungen'][$row['name']]['erwBuchungsID'] = $row['erwBuchungsID'];
          $this->lookup['erwarteteBuchungen'][$row['name']]['serchRegEx'] = json_decode($row['serchRegEx'],true);
          $this->lookup['erwarteteBuchungen'][$row['name']]['dateRule'] = $row['dateRule'];
          $this->lookup['erwarteteBuchungen'][$row['name']]['expectedDayOfMonth'] = $row['expectedDayOfMonth'];
          $this->lookup['erwarteteBuchungen'][$row['name']]['dateStart'] = $row['dateStart'];
          $this->lookup['erwarteteBuchungen'][$row['name']]['dateEnd'] = $row['dateEnd'];
      }
      
      $sql = "SELECT * FROM umsaetzeGiroKonto WHERE buchungstag > date('".$startDate."','unixepoch') AND buchungstag < date('".$endDate."','unixepoch')";
      print($sql."\n");
      $sth = $this->db->prepare( $sql );
      $sth->execute();
      $buchungen = $sth->fetchAll(PDO::FETCH_ASSOC);

      #print(count($buchungen));
      
      foreach ($this->lookup['erwarteteBuchungen'] as $erwBuchungName=>$erwBuchung) {
        
          $isErwarteteBuchung = 0;
          $returnText = "";
          #foreach ($this->lookup['erwarteteBuchungen'] as $indexUnused=>$erwBuchung) {
          foreach ($buchungen as $buchung) {
              if($this->checkErwBuchung($buchung,$erwBuchung) === 1) {
                $isErwarteteBuchung = 1;
                break;
              }
          }
          if ($isErwarteteBuchung === 1) {
              $this->log->info("found erwartete Buchung ".$erwBuchungName." for Buchung ".substr($buchung['buchungstext'],0,30)." value= ".$buchung['changeValue']."...");
            
          } else {
              $this->log->info("did not find erwartete Buchung".$erwBuchungName);
          }
      }
      
      
      
      
    }
    
    
    
    public function checkErwBuchungen ($startDate,$endDate) {
      // get erwarteteBuchnungn from table
        $sth = $this->db->prepare("SELECT erwBuchungsID, name,serchRegEx,dateRule,expectedDayOfMonth,dateStart,dateEnd FROM erwarteteBuchungen");
        $sth->execute();
        $data = $sth->fetchAll(PDO::FETCH_ASSOC);
        
        
        
        $startDay = date("d",$startDate);
        $endDay = date("d",$endDate);
        
        $deltaDays = date("d",$endDate-$startDate);
        print ("$deltaDays\n");
        
        
        $startMonth = date("d",$startDate);
        $endMonth = date("d",$endDate);
        
        foreach ($data as $row) {
            
            $this->lookup['erwarteteBuchungen'][$row['name']] = array();  
            $this->lookup['erwarteteBuchungen'][$row['name']]['erwBuchungsID'] = $row['erwBuchungsID'];
            $this->lookup['erwarteteBuchungen'][$row['name']]['serchRegEx'] = json_decode($row['serchRegEx'],true);
            $this->lookup['erwarteteBuchungen'][$row['name']]['dateRule'] = $row['dateRule'];
            $this->lookup['erwarteteBuchungen'][$row['name']]['expectedDayOfMonth'] = $row['expectedDayOfMonth'];
            $this->lookup['erwarteteBuchungen'][$row['name']]['dateStart'] = $row['dateStart'];
            $this->lookup['erwarteteBuchungen'][$row['name']]['dateEnd'] = $row['dateEnd'];
        }
        
        $sql = "SELECT * FROM umsaetzeGiroKonto WHERE buchungstag > date('".$startDate."') AND buchungstag < date('".$endDate."')";
        print($sql."\n");
        $sth = $this->db->prepare( $sql );
        $sth->execute();
        $buchungen = $sth->fetchAll(PDO::FETCH_ASSOC);
        
        #print(count($buchungen));
        
        #foreach ($buchungen as $buchung) {
        foreach ($this->lookup['erwarteteBuchungen'] as $erwBuchungName=>$erwBuchung) {
          $isErwarteteBuchung = 0;
          $returnText = "";
          #foreach ($this->lookup['erwarteteBuchungen'] as $indexUnused=>$erwBuchung) {
          foreach ($buchungen as $buchung) {
              if($this->checkErwBuchung($buchung,$erwBuchung) === 1) {
                $isErwarteteBuchung = 1;
                break;
              }
          }
          if ($isErwarteteBuchung === 1) {
              $this->log->info("found erwartete Buchung ".$erwBuchungName." for Buchung ".substr($buchung['buchungstext'],0,30)." value= ".$buchung['changeValue']."...");
            
          } else {
              $this->log->info("did not find erwartete Buchung".$erwBuchungName);
          }
        }
      
      // get umsaetze betweenn start and end date
      
      // loop over umsaezte, check with regex if expected day of month already reached
      
      // if found, check value
      
      // else: warn
      
      
      
      
      
      
      
    }
    
    
    
    private function filterMandatName($mandatName) {
        if(strlen($mandatName) < 2) {
            print("strange $mandatName\n");
        }
        
        $newMandatName = $mandatName;
        $regexArray = array();
        $regexArray['/VODAFONE/i'] = "Vodafone";
        $regexArray['/SWB/'] = "SWB";
        $regexArray['/SATURN/i'] = "SATURN";
        $regexArray['/RUNDFUNK/i'] = "RUNDFUNK";
        $regexArray['/ROSSMANN/i'] = "ROSSMANN";
        $regexArray['/REWE SAGT/i'] = "REWE Markt";
        $regexArray['/(NETTO-EINFACH|NETTO SAGT|NETTO MARKEN)/'] = "NETTO Markt";
        $regexArray['/PENNY SAGT/i'] = "PENNY Markt";
        $regexArray['/ALDI SAGT/i'] = "ALDI Markt";
        $regexArray['/COMBI\s/i'] = "Combi Markt";
        $regexArray['/DANKE.*IHR LIDL/i'] = "LIDL Markt";
        $regexArray['/(SPAR-MARKT|SPAR DIREKT)/'] = "SPAR Markt";
        $regexArray['/MEDIA MARKT/i'] = "Media Markt";
        $regexArray['/LUISE.*BURWIG/i'] = "Luise Burwig";
        $regexArray['/(LHK BREMEN|LANDESHAUPTK.*BREM)/i'] = "Landeshauptkasse Bremen";
        $regexArray['/KARSTADT/'] = "KARSTADT";
        $regexArray['/IKEA/i'] = "IKEA";
        $regexArray['/HORNBACH/i'] = "Hornbach";
        $regexArray['/HOLAB/i'] = "Hol Ab";
        $regexArray['/Fitness Life Bremen/i'] = "Fitness Life Bremen GmbH";
        $regexArray['/FINANZAMT BREM/i'] = "Finanzamt Bremen";
        $regexArray['/STADTAMT BREMEN/i'] = "Stadtamt Bremen";
        $regexArray['/EWS SCH.*AU VERTRIEBS/i'] = "EWS Schoenau GmbH";
        $regexArray['/EWE TEL GmbH/i'] = "EWE Tel GmbH";
        $regexArray['/(ELAN-AUSY|ELAN GMBH)/i'] = "ELAN GmbH";
        $regexArray['/AIRBUS OPERAT/i'] = "Airbus Operations GmbH";
        $regexArray['/SHELL/'] = "SHELL Tankstelle";
        $regexArray['/ARAL/'] = "ARAL Tankstelle";
        $regexArray['/JET-TANK/'] = "JET Tankstelle";
        $regexArray['/Abrechnung \d\d\.\d\d\.\d+ siehe Anlage/'] = "Abrechnung DKB";

        $matchesCounter = 0;
        
        foreach ($regexArray as $regex => $newText) {
            if ( preg_match($regex,$mandatName,$matches) === 1) { 
                $matchesCounter += 0;
                $newMandatName = $newText;
            }
        }
        
        if( $matchesCounter > 1) {
            print("found $matchesCounter too often for $mandatName\n");
            exit(0);
        }
        
        return  $newMandatName;
    }

    private function filterBuchungsArtName($buchungsArtName) {
        if(strlen($buchungsArtName) < 2) {
            print("strange $mandatName\n");
        }
        
        $newBuchungsArtName = $buchungsArtName;
        $regexArray = array();
        $regexArray['/Zahlungseingang/i'] = "Zahlungseingang";
        $regexArray['/Abrechnung \d\d\.\d\d\.\d+/i'] = "Abrechnung DKB";
        $regexArray['/Kartenzahlung/i'] = "Kartenzahlung";
        $regexArray['/Lastschrift/i'] = "Lastschrift";
        $regexArray['/Lohn.*Gehalt/i'] = "Lohn Gehalt";
        $regexArray['/Überweisung/i'] = "Überweisung";
        $regexArray['/Berichtigung/i'] = "Berichtigung";
        $regexArray['/Scheckeinzug/i'] = "Scheckeinzug";
        $regexArray['/Rückbuchung/i'] = "Rückbuchung";
        $regexArray['/GUTSCHRIFT/i'] = "Gutschrift";
        $regexArray['/Kreditkartenabr/i'] = "Kreditkartenabrechnung";
        $regexArray['/Verfügung Geldautomat/i'] = "Verfügung Geldautomat";
        $matchesCounter = 0;
        
        foreach ($regexArray as $regex => $newText) {
            if ( preg_match($regex,$buchungsArtName,$matches) === 1) { 
                $matchesCounter += 0;
                $newBuchungsArtName = $newText;
            }
        }
        
        if( $matchesCounter > 1) {
            print("found $matchesCounter too often for $buchungsArtName\n");
            exit(0);
        }
        return  $newBuchungsArtName;
    }    

    private function addMandat($mandatName,$CRED,$MREF,$serchRegEx,$umsatzIDsJsonArray) {
        
        $this->log->debug('addMandat',array($mandatName,$CRED));
        $insert = 'INSERT INTO mandate (mandatName, CRED, MREF, serchRegEx, umsatzIDsJsonArray)  VALUES (:mandatName,:CRED,:MREF,:serchRegEx,:umsatzIDsJsonArray )';
        $stmt = $this->db->prepare($insert);

        $stmt->bindParam(':mandatName', $mandatName);
        $stmt->bindParam(':CRED', $CRED);
        $stmt->bindParam(':MREF', $MREF);
        $stmt->bindParam(':serchRegEx', $serchRegEx);
        $stmt->bindParam(':umsatzIDsJsonArray', $umsatzIDsJsonArray);
        
        $stmt->execute(); 
        $this->lookup['mandate'][$mandatName] = array();
        $this->lookup['mandate'][$mandatName]['byCRED'][$CRED] = array();
        $this->lookup['mandate'][$mandatName]['byCRED'][$CRED]['MREF'] = $MREF;
        $this->lookup['mandate'][$mandatName]['byCRED'][$CRED]['mandatID'] = $this->db->lastInsertId();
        $this->lookup['mandate'][$mandatName]['byCRED'][$CRED]['umsatzIDsJsonArray'] = array();
        $this->lookup['mandate'][$mandatName]['byMREF'][$MREF] = array();
        $this->lookup['mandate'][$mandatName]['byMREF'][$MREF]['CRED'] = $CRED;
        $this->lookup['mandate'][$mandatName]['byMREF'][$MREF]['mandatID'] = $this->db->lastInsertId();
        $this->lookup['mandate'][$mandatName]['byMREF'][$MREF]['umsatzIDsJsonArray'] = array();
    }
    
    // removes empty lines and unnecessary footer
    private function filterBuchungsText ($text) {
        $textArray = preg_split("/\r?\n/",$text);
        $returnArray = array();
        foreach ($textArray as $row) {
            if (preg_match("/^\s*$/",$row,$matches) ===1 )  continue;
            if (preg_match("/\s*DEUTSCHE KREDITBANK AG\s+IBAN/",$row,$matches) ===1 ) {
                break;
            }
            array_push($returnArray,$row);
        }
        return $returnArray;
    }

    private function analyzeSepa ($text) {

        $sepaKeyWords = array();
        $sepaKeyWords["EREF"] = 0;
        $sepaKeyWords["MREF"] = 0;
        $sepaKeyWords["CRED"] = 0;
        $sepaKeyWords["SVWZ"] = 0;
        $sepaKeyWords["ABWA"] = 0;
        $sepaKeyWords["KREF"] = 0;
        $positions  = array();
        foreach ($sepaKeyWords as $keyWord => $dummy) {
            $pos = strpos($text,$keyWord."+");
            if ($pos !== false) {
                $sepaKeyWords[$keyWord] = $pos;
                array_push($positions,$pos);
            }
            else {
                // remove the sepa key that is not found
                unset($sepaKeyWords[$keyWord]);
            }
        }
        array_multisort($sepaKeyWords, $positions );
        $sepaKeys = array_keys($sepaKeyWords);
 
        if (count($sepaKeyWords) > 0) {
            for ($sepaKeyIndex = 0; $sepaKeyIndex < count($sepaKeys)-1; $sepaKeyIndex++) {
                $startpos = $sepaKeyWords[$sepaKeys[$sepaKeyIndex]];
                $endpos = $sepaKeyWords[$sepaKeys[$sepaKeyIndex+1]];
                $sepaKeyWords[$sepaKeys[$sepaKeyIndex]] = substr($text,$startpos+5,$endpos-$startpos-5);
            }

            $startpos = $sepaKeyWords[$sepaKeys[count($sepaKeys)-1]];
            $sepaKeyWords[$sepaKeys[count($sepaKeys)-1]] = substr($text,$startpos+5);
            // ensure that all sepa keys are initialized 
            foreach (array("CRED","MREF","EREF","SVWZ","ABWA","KREF") as $sepaKey) {
                if (array_search($sepaKey,$sepaKeys,true) === false) {
                    $sepaKeyWords[$sepaKey] = NULL ; #"unbekannt";
                } else {
                    $sepaKeyWords[$sepaKey] = trim(preg_replace("/\n/","",$sepaKeyWords[$sepaKey]));
                }
            }
        }
        
        foreach (array("CRED","MREF","EREF","SVWZ","ABWA","KREF") as $sepaKey) {
            if (! isset($sepaKeyWords[$sepaKey])) {
                $sepaKeyWords[$sepaKey] = NULL ; #"unbekannt";
            }
        }
        return $sepaKeyWords;
    }

    public function analyzeKontoAuszugFile($origfile) {
        $this->log->debug('analyzeKontoAuszugFile: '.$origfile."\n");
        $file = basename($origfile);
        print ("reading $origfile\n");
        if (in_array($file, array_keys($this->lookup['kontoauszuege'])) == true){
            print ("already in, will not add kontoauszugdatei, id is ".$this->lookup['kontoauszugdateien'][$file]."\n");
            return;
        }
        
        $this->currentFile = array();
        $this->currentFile["name"] = $file;
        $this->currentFile["pages"] = array();

        $fileData = $this->file_get_contents_utf8($origfile);
        if ( ! preg_match_all("/Bu.Tag\s+Wert\s+(Wir haben für Sie gebucht)\s+(Belastung in EUR)\s+(Gutschrift in EUR)/m", $fileData, $matches,PREG_SET_ORDER+PREG_OFFSET_CAPTURE)  > 0) {
            throw new Exception ("could not find pages in file $origfile\n");
        }

        $pages = array();
        $pages[0] = array();
        $pages[0]["start"]      = $matches[0][0][1];
        
        if (isset ($matches[1]) ) {
        
            $pages[0]["end"]        = $matches[1][0][1];
        } else {
            
            if ( ! preg_match("/\n(ALTER KONTOSTAND)\s+\d/",$fileData,$alterKontoStandMatch,PREG_OFFSET_CAPTURE) === 1) throw new Exception("Could not find ALTER KONTOSTAND in file $origfile\n");
            $pages[0]["end"]  = $alterKontoStandMatch[0][1];
            
        }
        $pages[0]["textPos"]    = $matches[0][1][1] - $matches[0][0][1];
        $pages[0]["NegPos"]     = $matches[0][2][1] - $matches[0][0][1];
        $pages[0]["PosPos"]     = $matches[0][3][1] - $matches[0][0][1];
        $pages[0]["text"]       = array();
        $pages[0]["text"]       = $this->filterBuchungsText( substr($fileData,$pages[0]["start"],$pages[0]["end"]-$pages[0]["start"]));
        
        for ($i = 1; $i < count($matches)-1; $i++) {
            $pages[$i] = array();
            $pages[$i]["start"]     = $matches[$i][0][1];
            $pages[$i]["end"]       = $matches[$i+1][0][1];
            $pages[$i]["textPos"]   = $matches[$i][1][1] - $matches[$i][0][1];
            $pages[$i]["NegPos"]    = $matches[$i][2][1] - $matches[$i][0][1];
            $pages[$i]["PosPos"]    = $matches[$i][3][1] - $matches[$i][0][1];
            $pages[$i]["text"]      = array();
            $pages[$i]["text"]      = $this->filterBuchungsText( substr($fileData,$pages[$i]["start"],$pages[$i]["end"]-$pages[$i]["start"]) );
        }

        $i = count($matches)-1;
        $pages[$i] = array();
        $pages[$i]["textPos"]  = $matches[$i][1][1] - $matches[$i][0][1];
        $pages[$i]["NegPos"]   = $matches[$i][2][1] - $matches[$i][0][1];
        $pages[$i]["PosPos"]   = $matches[$i][3][1] - $matches[$i][0][1];
        $pages[$i]["start"] = $matches[$i][0][1];
        
        if ( ! preg_match("/\n(ALTER KONTOSTAND)\s+\d/",$fileData,$matches,PREG_OFFSET_CAPTURE) === 1) throw new Exception("Could not find ALTER KONTOSTAND in file $origfile\n");

        $pages[$i]["end"]  = $matches[0][1];
        $pages[$i]["text"] = array();
        $pages[$i]["text"]   = $this->filterBuchungsText( substr($fileData,$pages[$i]["start"],$pages[$i]["end"]-$pages[$i]["start"]));
        $this->currentFile["pages"] = $pages;

        if (preg_match("/NEUER KONTOSTAND\s+(.*)\sH\sEUR/", $fileData, $matches)  ===1) {
            $kontoStand = str_replace(".","",$matches[1]);
            $kontoStand = floatval(str_replace(",",".",$kontoStand));
            $this->currentFile["kontoStand"] = $kontoStand;
        }
        
        if (preg_match("/ALTER KONTOSTAND\s+(.*)\sH\sEUR/", $fileData, $matches)  ===1) {
            $kontoStandAlt = str_replace(".","",$matches[1]);
            $kontoStandAlt = floatval(str_replace(",",".",$kontoStandAlt));
            $this->currentFile["kontoStandAlt"] = $kontoStandAlt;
        }
        
        if (preg_match("/Kontoauszug Nummer \d+ \/ \d+ vom (\d\d)\.(\d\d)\.(\d+) bis (\d\d)\.(\d\d)\.(\d+)/", $fileData, $matches)  ===1 )  {
            $this->currentFile["beginnDatum"] = date("c",mktime(12,0,0,$matches[2],$matches[1],$matches[3]));
            
            $this->currentFile["beginnJahr"] = intval($matches[3]);
            
            $this->currentFile["endDatum"] = date("c",mktime(12,0,0,$matches[5],$matches[4],$matches[6]));
            
            $this->currentFile["endJahr"] = intval($matches[6]);
                        
            
            
            #$this->currentFile["year"] = $matches[3];
        } else throw new Exception("Could not find Kontoauszug Datums in file $origfile\n");

        $this->currentFile["buchungsIDsArray"] = array();
        # analyze text of each page for buchungen
        $buchungen = array();
        $buchung = array();
        $buchung["buchungsart"] = Null;
        $buchung["text"] = Null;
        $buchung["buchungsTag"] = Null;
        $buchung["werstellungsTag"] = Null;
        $buchung["changeValue"] = Null;
        $buchung["absValue"] = $this->absValue;
        $firstTextLine = false;

        foreach ($this->currentFile["pages"] as $pageIndex => $page) {
            $firstBuchungOnPage = True;
            $lineCounter = 0;
            foreach ($page["text"] as $line) {
                $lineCounter += 1;
                if (preg_match("/^Bu.Tag\s+Wert.*(Belastung in EUR)/", $line, $matches,PREG_OFFSET_CAPTURE)  ===1) { continue;}
                // check if beginning of buchung (with a value in the line)
                if (preg_match("/^\s*(\d\d)\.(\d\d)\.\s+(\d\d)\.(\d\d)\.\s+(.*)\s+(\d+\.?\d*,\d+)/", $line, $matches,PREG_OFFSET_CAPTURE)  === 1 ) {
                    # if there already is data from previous lines and the data is complete, store buchung
                    $addBuchung = True;
                    foreach (array("buchungsart","buchungsTag","werstellungsTag","changeValue") as $key ) {
                        if(is_null($buchung[$key]) ) {
                            $addBuchung = False;
                            #print("$pageIndex not adding! $key of buchung no ".(count($buchungen)+1)."\n");
                        }
                    }
                    if ($addBuchung === True) {
                        // add buchung
                        $analyzeSepaResult = $this->analyzeSepa($buchung["text"]);
                        $buchung["sepa"] = $analyzeSepaResult;
                        
                        if (!isset($buchung["mandatName"])) {
                            $buchung["mandatName"] = $buchung["buchungsart"];
                        }
                   
                        array_push($buchungen,$buchung);
                        #$buchung = array();
                        $buchung["buchungsart"] = Null;
                        $buchung["text"] = Null;
                        $buchung["buchungsTag"] = Null;
                        $buchung["werstellungsTag"] = Null;
                        $buchung["changeValue"] = Null;
                        
                    } else {
               
                        if (($firstBuchungOnPage == True) and ($pageIndex > 0)) {
                            // the current data hold in $buchung must be a fragment from the previous page;
                            // so we add it there an clear it
                            
                            #$this->log->debug('found Buchung fragment on page '.($pageIndex+1).": ".$buchung["buchungsart"]);
                            
                            // find out last index of previous page
                            $buchungsIndexArray = array_keys($this->currentFile["pages"][$pageIndex-1]["buchungen"]);
                            $lastIndex = $buchungsIndexArray[count($buchungsIndexArray)-1];
                            // add text to this buchung of previous page
                            $this->currentFile["pages"][$pageIndex-1]["buchungen"][$lastIndex]["text"] .= $buchung["text"];
                            $analyzeSepaResult = $this->analyzeSepa($this->currentFile["pages"][$pageIndex-1]["buchungen"][$lastIndex]["text"]);
                            // add sepa data buchung of previous page
                            $this->currentFile["pages"][$pageIndex-1]["buchungen"][$lastIndex]["sepa"] = $analyzeSepaResult;

                            $firstBuchungOnPage = False;
                                $buchung["buchungsart"] = Null;
                            $buchung["text"] = Null;
                            $buchung["buchungsTag"] = Null;
                            $buchung["werstellungsTag"] = Null;
                            $buchung["changeValue"] = Null;
 
                        } else {
                            #print_r($buchung);
                        }
                    }
                    #$buchung["buchungsTag"]     = date("c",mktime(12,0,0,$matches[2][0],$matches[1][0], $this->currentFile["year"] ));
                    
                    
                    
                    $month = $matches[2][0];
                    
                    if (intval($month) == 12) {
                      $buchung["buchungsTag"] = date("Y-m-d",strtotime($this->currentFile["beginnJahr"]."-".$matches[2][0]."-".$matches[1][0]));
                      
                    } else {
                      $buchung["buchungsTag"] = date("Y-m-d",strtotime($this->currentFile["endJahr"]."-".$matches[2][0]."-".$matches[1][0]));
                      
                    }
                    
                    
                    #$buchung["buchungsTag"] = date("Y-m-d",strtotime($this->currentFile["year"]."-".$matches[2][0]."-".$matches[1][0]));
                    

                    
                    $month = $matches[4][0];
                    if (intval($month) == 12) {
                      $buchung["werstellungsTag"] = date("Y-m-d",strtotime($this->currentFile["beginnJahr"]."-".$matches[4][0]."-".$matches[3][0]));
                    } else {
                      $buchung["werstellungsTag"] = date("Y-m-d",strtotime($this->currentFile["endJahr"]."-".$matches[4][0]."-".$matches[3][0]));
                    }
                    
                    
                    
                    $buchung["buchungsart"] = $this->filterBuchungsArtName(trim($matches[5][0]));
                    $changeValuePosition   = $matches[6][1];
                    $changeValue           = str_replace(".","",$matches[6][0]);
                    $changeValue           = floatval(str_replace(",",".",$changeValue));
                    if (($changeValuePosition+strlen($changeValue)) < $pages[$pageIndex]["PosPos"]) { 
                        $changeValue = $changeValue * (-1);
                    }

                    $buchung["changeValue"] = $changeValue;
                    $buchung["absValue"] += $changeValue;
                    $firstTextLine = true;
                    continue;
                }
                
                if (preg_match("/^\s*(\d\d)\.(\d\d)\.\s+(\d\d)\.(\d\d)\.\s+(.*)/", $line, $matches,PREG_OFFSET_CAPTURE)  === 1 ) {
                    $buchung["text"] .= trim($matches[5][0])." ";
                    continue;
                } 

                // mandat
                if (preg_match("/^\s*(.*)\s*/", $line, $matches, PREG_OFFSET_CAPTURE)  === 1 ) {
                    if (($page["textPos"]-2 < $matches[1][1]) and ($matches[1][1] < $page["textPos"]+2)) { 
                        $buchung["text"] .= trim($matches[1][0])." ";
                        if ($firstTextLine == true) {
                            $buchung["mandatName"] = $this->filterMandatName(trim($matches[1][0]));
                            $firstTextLine = false;
                        }
                    } else {
                        throw new Exception ("strange text pos in file $origfile: ".$line." ".$page["textPos"]."/".$matches[1][1]);
                    }
                }
            }
            
            // check if last data is a buchung
            
            $addBuchung = True;
            foreach (array("buchungsart","buchungsTag","werstellungsTag","changeValue") as $key ) { #"text"
                if(is_null($buchung[$key]) ) {
                    $addBuchung = False;
                    
                }
            }
            if ($addBuchung === True) {
                // add buchung
                $analyzeSepaResult = $this->analyzeSepa($buchung["text"]);
                $buchung["sepa"] = $analyzeSepaResult;
                if (!isset($buchung["mandatName"])) {
                    $buchung["mandatName"] = $buchung["buchungsart"];
                }
                array_push($buchungen,$buchung);
                $buchung["buchungsart"] = Null;
                $buchung["text"] = Null;
                $buchung["buchungsTag"] = Null;
                $buchung["werstellungsTag"] = Null;
                $buchung["changeValue"] = Null;
            } else {
                #print ("not adding ");
                #print_r($buchung);
                
                # seems to be a fragment. add this to last 
                
                #$this->log->debug('found Buchung fragment on page '.($pageIndex+1).": ".$buchung["buchungsart"]);

                // find out last index of previous page
                $buchungsIndexArray = array_keys($this->currentFile["pages"][$pageIndex-1]["buchungen"]);
                $lastIndex = $buchungsIndexArray[count($buchungsIndexArray)-1];
                // add text to this buchung of previous page
                $this->currentFile["pages"][$pageIndex-1]["buchungen"][$lastIndex]["text"] .= $buchung["text"];
                $analyzeSepaResult = $this->analyzeSepa($this->currentFile["pages"][$pageIndex-1]["buchungen"][$lastIndex]["text"]);
                // add sepa data buchung of previous page
                $this->currentFile["pages"][$pageIndex-1]["buchungen"][$lastIndex]["sepa"] = $analyzeSepaResult;
            }
            
            $this->currentFile["pages"][$pageIndex]["buchungen"] = $buchungen;
            $buchungen = array();
        }    
        
        $this->absValue = $buchung["absValue"];
        
        if (abs($this->currentFile["kontoStand"] - $this->absValue) > 0.001) {
          print ($this->currentFile["kontoStand"]."\n".$this->absValue."\n");
          throw new Exception ("not matching old and new:");
        }

        # for debugging
        file_put_contents("currentFile.txt", json_encode($this->currentFile ,JSON_UNESCAPED_UNICODE+JSON_PRETTY_PRINT ) );

        # add Kontoasuzug to table kontoauszuege
        $insert = 'INSERT INTO kontoauszuege (beginnDatum, endDatum, kontoStandAlt, kontoStand, filename, buchungsIDsArray)  VALUES (:beginnDatum, :endDatum, :kontoStandAlt, :kontoStand, :filename, :buchungsIDsArray)';
        $stmt = $this->db->prepare($insert);
        $stmt->bindParam(':beginnDatum', $this->currentFile["beginnDatum"]);
        $stmt->bindParam(':endDatum', $this->currentFile["endDatum"]);
        $stmt->bindParam(':kontoStandAlt', $this->currentFile["kontoStandAlt"]);
        $stmt->bindParam(':kontoStand', $this->currentFile["kontoStand"]);
        $stmt->bindParam(':filename',  $this->currentFile["name"]);
        $jsonArray = json_encode($this->currentFile["buchungsIDsArray"]);
        $stmt->bindParam(':buchungsIDsArray', $jsonArray );
        $stmt->execute(); 
        $kontoauszugID = $this->db->lastInsertId();

        // add buchungen in umsaetzeGiroKonto. store each id in array
        
        // for later check store summed deltas
        $delta = 0;
        foreach ($this->currentFile["pages"] as $pageIndex => $dummy) {
            unset ($this->currentFile["pages"][$pageIndex]["buchungen"]["ersteBuchungIstFragment"]);
            foreach ($this->currentFile["pages"][$pageIndex]["buchungen"] as $buchungsIndex => $buchung) {
                
                // check buchungsart. Add if necessary
                $buchungsartID = -1;
                if ( isset($this->lookup['buchungsArten'][$buchung["buchungsart"]]) ) {
                    $buchungsartID = $this->lookup['buchungsArten'][$buchung["buchungsart"]]['buchungsartID'];
                } else {
                    $insert = 'INSERT INTO buchungsArten (name, info) VALUES (:name, "found first time in file '.$this->currentFile["name"].'")';
                    $stmt = $this->db->prepare($insert);
                    $stmt->bindParam(':name', $buchung["buchungsart"]);
                    $stmt->execute(); 
                    $buchungsartID = $this->db->lastInsertId();
                    
                    $this->lookup['buchungsArten'][$buchung["buchungsart"]] = array();  
                    $this->lookup['buchungsArten'][$buchung["buchungsart"]]['buchungsartID'] = $buchungsartID;
                    $this->lookup['buchungsArten'][$buchung['buchungsart']]['umsatzIDs'] = array();
                    $this->lookup['buchungsArten'][$buchung["buchungsart"]]['info'] = "found first time in file ".$this->currentFile["name"];
                }
                
                // set to zero. This analyis will be done when all data is in the db
                $buchungsgruppenID = 0;
                // check mandatID
                
                $mandatID = 0;
                  foreach ($this->lookup['mandate'] as $possibleMandatID => $dummy) {
                    $mandatName = $this->lookup['mandate'][$possibleMandatID]['mandatName'];
                    $CRED = $this->lookup['mandate'][$possibleMandatID]['CRED'];
                    $MREF = $this->lookup['mandate'][$possibleMandatID]['MREF'];
                      if ($mandatName == $buchung["mandatName"]) {
                        if (($CRED == $buchung["sepa"]["CRED"]) and ($MREF == $buchung["sepa"]["MREF"])) {
                              $mandatID = $possibleMandatID;
                            break;
                        }
                    }
                }
                
               
                if ($mandatID == 0) {
                    
                    // no mandatID found, add as new
                    #print("add >".$buchung["mandatName"]."<\n");
                    $insert = 'INSERT INTO mandate (mandatName, CRED, MREF, umsatzIDsJsonArray) VALUES (:mandatName, :CRED, :MREF, "[]")';
                    $stmt = $this->db->prepare($insert);
                    $stmt->bindParam(':mandatName', $buchung["mandatName"]);
                    $stmt->bindParam(':CRED', $buchung["sepa"]["CRED"]);
                    $stmt->bindParam(':MREF', $buchung["sepa"]["MREF"]);
                    $stmt->execute(); 
                    $mandatID = $this->db->lastInsertId();
                    if (! isset($this->lookup['mandate'][$mandatID])) {
                        $this->lookup['mandate'][$mandatID] = array();
                    }
                    $this->lookup['mandate'][$mandatID]['mandatName'] = $buchung['mandatName'];
                    $this->lookup['mandate'][$mandatID]['MREF'] = $buchung["sepa"]["MREF"];
                    $this->lookup['mandate'][$mandatID]['CRED'] = $buchung["sepa"]["CRED"];
                    $this->lookup['mandate'][$mandatID]['umsatzIDsJsonArray'] = "[]";
                }
                
                #$insert = 'INSERT INTO umsaetzeGiroKonto (buchungstag, wertstellungstag, buchungsartID,buchungsGruppenIDs, changeValue, absValue, buchungstext, SVWZ,EREF, mandatID , kontoauszugdateiID ) VALUES (:buchungstag,:wertstellungstag,:buchungsartID,:buchungsGruppenIDs,:changeValue,:absValue, :buchungstext,:SVWZ,:EREF,:mandatID,:kontoauszugdateiID)';
                $insert = 'INSERT INTO umsaetzeGiroKonto (buchungstag, wertstellungstag, buchungsartID,buchungsGruppenIDs, changeValue, absValue, buchungstext, sepaJSON, mandatID , kontoauszugdateiID ) VALUES (:buchungstag,:wertstellungstag,:buchungsartID,:buchungsGruppenIDs,:changeValue,:absValue, :buchungstext,:sepaJSON,:mandatID,:kontoauszugdateiID)';
                $stmt = $this->db->prepare($insert);
                $stmt->bindParam(':buchungstag', $buchung["buchungsTag"]);
                $stmt->bindParam(':wertstellungstag', $buchung["werstellungsTag"]);
                $stmt->bindParam(':buchungsartID', $buchungsartID );
                $stmt->bindParam(':buchungsGruppenIDs', $buchungsgruppenID);
                $stmt->bindParam(':changeValue', $buchung["changeValue"]);
                $stmt->bindParam(':absValue', $buchung["absValue"]);
                $stmt->bindParam(':buchungstext', $buchung["text"]);
                #$stmt->bindParam(':SVWZ', $buchung["sepa"]["SVWZ"]);
                #$stmt->bindParam(':EREF', $buchung["sepa"]["EREF"]);
                
                $sepaArray = array();
                if (!is_null($buchung["sepa"]["SVWZ"])){
                  $sepaArray["SVWZ"] = $buchung["sepa"]["SVWZ"];
                  
                }
                if (!is_null($buchung["sepa"]["EREF"])) {
                  $sepaArray["EREF"] = $buchung["sepa"]["EREF"];
                  
                }
                $sepaJSON = json_encode($sepaArray);
                $stmt->bindParam(':sepaJSON', $sepaJSON);
                $stmt->bindParam(':mandatID', $mandatID);
                $stmt->bindParam(':kontoauszugdateiID', $kontoauszugID);
                $stmt->execute(); 
                  $delta += $buchung["changeValue"];
                $umsatzID = $this->db->lastInsertId();

                // add umsatzID to buchungsArten table
                
                array_push($this->lookup['buchungsArten'][$buchung["buchungsart"]]['umsatzIDs'],$umsatzID);
                  
            }
        }
 
        $deltaBank = floatval($this->currentFile["kontoStand"]- $this->currentFile["kontoStandAlt"]);
        if (abs($delta - $deltaBank) > 0.01) {
            throw new Exception ("not the same delta! $delta !== $deltaBank \n".abs(($delta) - ($deltaBank)));
        }
     }

}
