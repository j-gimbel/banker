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
    #$log->pushProcessor(new MemoryUsageProcessor());
    
    $p = new parser();
    $p->log = $log;
    $p->log->info('Creating parser');
    $p->connectToDB();
    #$p->createTable(["buchungsGruppen"]);
        
    $p->makeLookupStructure();
    