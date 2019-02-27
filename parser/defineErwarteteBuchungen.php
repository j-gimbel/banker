<?php

// opcache wieder an : /etc/php/7.0/apache2/conf.d/10-opcache.ini

#echo ("<pre>");

// Melde alle PHP Fehler
error_reporting(-1);
date_default_timezone_set("Europe/Berlin");
require_once('../vendor/autoload.php');
require_once "class.banker.php";

#print(__DIR__ . '/vendor/autoload.php');
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\JsonFormatter;
#use SQLite3;
try {
    

    // create a log channel
    $log = new Logger('parser');
    $streamHandler = new StreamHandler('parser.log', Logger::DEBUG);
    $log->pushHandler($streamHandler);
    
    $streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
    $output = "[%datetime%] %channel%.%level_name%: %message% \n"; #%context% %extra%
    $formatter = new LineFormatter($output);
    $streamHandler->setFormatter($formatter);
    $log->pushHandler($streamHandler);
    
    $log->pushProcessor(new IntrospectionProcessor());
    
    $p = new parser();
    $p->log = $log;
    $p->log->info('Creating parser');
    $p->connectToDB();
    $p->createTable(["erwarteteBuchungen"]);
    $p->makeLookupStructure();
    
    $erwBuchungen = array( );
    array_push($erwBuchungen,array("name"=>"Kredit1", "serchRegEx"=>json_encode(array("buchungstext"=>"/Commerzbank.*AZ\s*7392655029.*Tilgung/","changeValue"=>"-1047.38")),"dateRule"=>"monthly","expectedDayOfMonth"=>"28...31","dateStart"=>"2018-10-30","dateEnd"=>"2028-10-30")); 
    
    array_push($erwBuchungen,array("name"=>"Kredit2", "serchRegEx"=>json_encode(array("buchungstext"=>"/Commerzbank.*AZ\s*7392655010.*Tilgung/","changeValue"=>"-342.75")),"dateRule"=>"monthly","expectedDayOfMonth"=>"28...31","dateStart"=>"2018-10-30","dateEnd"=>"2028-10-30")); 
    
    array_push($erwBuchungen,array("name"=>"HausGeld1", "serchRegEx"=>json_encode(array("buchungstext"=>"/Hans-Peter Beck.*Buntentor.*073/","changeValue"=>"-220.0...-180.0")),"dateRule"=>"monthly","expectedDayOfMonth"=>"1...5","dateStart"=>"2013-01-01","dateEnd"=>"")); 
    
    array_push($erwBuchungen,array("name"=>"HausGeld2", "serchRegEx"=>json_encode(array("buchungstext"=>"/Hans-Peter Beck.*StPl/","changeValue"=>"-12...-3")),"dateRule"=>"monthly","expectedDayOfMonth"=>"1...5","dateStart"=>"2013-01-01","dateEnd"=>"")); 
    
    array_push($erwBuchungen,array("name"=>"Miete", "serchRegEx"=>json_encode(array("buchungstext"=>"/Miete.* Kornstra.*e/","changeValue"=>"950")),"dateRule"=>"monthly","expectedDayOfMonth"=>"01...05","dateStart"=>"2018-10-01","dateEnd"=>"")); 
    
    array_push($erwBuchungen,array("name"=>"SWB-Wasser", "serchRegEx"=>json_encode(array("buchungstext"=>"/SWB VERTRIEB BREMEN.*K-45342912.*A-97290919/","changeValue"=>"-7")),"dateRule"=>"monthly","expectedDayOfMonth"=>"20...26","dateStart"=>"2018-11-01","dateEnd"=>"")); 
    
    array_push($erwBuchungen,array("name"=>"EON-Gas", "serchRegEx"=>json_encode(array("buchungstext"=>"/E.ON Energie Deutschland.*VK.*232051930830/","changeValue"=>"-86")),"dateRule"=>"monthly","expectedDayOfMonth"=>"3..10","dateStart"=>"2018-11-01","dateEnd"=>"")); 
    
    array_push($erwBuchungen,array("name"=>"DWS-Riester", "serchRegEx"=>json_encode(array("buchungstext"=>"/DWS Investment GmbH.*T201691401/","changeValue"=>"-75")),"dateRule"=>"monthly","expectedDayOfMonth"=>"03..10","dateStart"=>"2018-11-01","dateEnd"=>"")); 
    
    array_push($erwBuchungen,array("name"=>"Airbus-Gehalt", "serchRegEx"=>json_encode(array("buchungstext"=>"/AIRBUS OPERATIONS.*Lohn.*Gehalt/","changeValue"=>"3000...6000")),"dateRule"=>"monthly","expectedDayOfMonth"=>"24..31","dateStart"=>"2014-03-01","dateEnd"=>"")); 
    
    foreach ($erwBuchungen as $erwBuchung) {
      
        $insert = 'INSERT INTO erwarteteBuchungen (name,serchRegEx,dateRule,expectedDayOfMonth,dateStart,dateEnd) VALUES (:name,:serchRegEx,:dateRule,:expectedDayOfMonth,:dateStart,:dateEnd)';
        $stmt = $p->db->prepare($insert);
        $stmt->bindParam(':name', $erwBuchung["name"]);
        $stmt->bindParam(':serchRegEx', $erwBuchung["serchRegEx"]);
        $stmt->bindParam(':dateRule', $erwBuchung["dateRule"]);
        $stmt->bindParam(':expectedDayOfMonth', $erwBuchung["expectedDayOfMonth"]);
        $stmt->bindParam(':dateStart', $erwBuchung["dateStart"]);
        $stmt->bindParam(':dateEnd', $erwBuchung["dateEnd"]);
        $stmt->execute(); 
    }
}
catch(PDOException $e) {
    // Print PDOException message
    echo "PDO exception in ".$e->getFile()." in ".$e->getLine().": ".$e->getMessage()."\n";
}

catch(Exception $e) {
    echo $e->getMessage()." in file ".$e->getFile()." line ".$e->getLine()."\n";
}
    