<?php

// opcache wieder an : /etc/php/7.0/apache2/conf.d/10-opcache.ini

#echo ("<pre>");

// Melde alle PHP Fehler
error_reporting(-1);
date_default_timezone_set("Europe/Berlin");
require_once('../vendor/autoload.php');
require_once "class.parser.php";

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
    #$log->pushProcessor(new MemoryUsageProcessor());
    
    $p = new parser();
    $p->log = $log;
    $p->log->info('Creating parser');
    $p->connectToDB();
    
    // new db
    $p->createTable(["all"]);
    $files = array();
    $files = glob('data//Kontoauszug_1013152713_Nr*.txt');
    asort($files);
    // only use one file for development
    #$files = array();
    #$files[0] = "data/Kontoauszug_1013152713_Nr_2013_004_per_2013_04_04_0.txt";
    
    $p->log->debug('used Files: ',$files);
    $p->addDataFromFiles($files);
    #print(json_encode($p->lookup['mandate']));
}
catch(PDOException $e) {
    // Print PDOException message
    echo "PDO exception in ".$e->getFile()." in ".$e->getLine().": ".$e->getMessage()."\n";
}

catch(Exception $e) {
    echo $e->getMessage()." in file ".$e->getFile()." line ".$e->getLine()."\n";
}



?>