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
    $streamHandler = new StreamHandler('deomoAnalysis.log', Logger::DEBUG);
    $log->pushHandler($streamHandler);
    
    $streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
    $output = "[%datetime%] %channel%.%level_name%: %message% \n"; #%context% %extra%
    $formatter = new LineFormatter($output);
    $streamHandler->setFormatter($formatter);
    $log->pushHandler($streamHandler);
    
    $log->pushProcessor(new IntrospectionProcessor());
    #$log->pushProcessor(new MemoryUsageProcessor());
    
    $p = new parser();
    $p->log = $log;
    $p->log->info('Creating parser');
    $p->connectToDB();
    
    #$startDate = date('Y-m-d',time());
    #$endDate = date('Y-m-d',time());
    
    #$curMonthTimeStamp = strtotime(date("Y-m"));
    
    $currentDate = date_parse(date("Y-m-d",time()));
    #$endDate = $currentDate;
    #$startDate = $currentDate;
    $curYear = $currentDate["year"];
    $curMonth = $currentDate["month"];

    #$tz = new DateTimeZone('Europe/Berlin');
    #$transitions = $tz->getTransitions(time());
    #print_r($transitions[0]); // Angaben zur aktuellen Zeit
    #print_r($transitions[1]); // Nächster Wechsel
    #print_r($transitions[2]); // Übernächster Wechsel etc.
    

    #$startDate = mktime(0,0,0,$curMonth,1,$curYear);
    #$endDate = mktime(0,0,0,$curMonth,30,$curYear);
    #print ($startDate."=".gmdate(DATE_ISO8601,$startDate)."\n");
    #print ($endDate."=".gmdate(DATE_ISO8601,$endDate)."\n");
    
    $startDate = new DateTime('last day of this month');  # or now
    $endDate = new DateTime('first day of this month');  # or now
    
    $p->log->info("start: ".$startDate->format("Y-m-d")." = ".$startDate->format("U")."\n");
    $p->log->info("end: ".$endDate->format("Y-m-d")." = ".$startDate->format("U")."\n");
        
    $p->checkErwBuchungen( $startDate->format("Y-m-d"),$endDate->format("Y-m-d")  );
    
    

}
catch(PDOException $e) {
    // Print PDOException message
    echo "PDO exception in ".$e->getFile()." in ".$e->getLine().": ".$e->getMessage()."\n";
}

catch(Exception $e) {
    echo $e->getMessage()." in file ".$e->getFile()." line ".$e->getLine()."\n";
}



?>