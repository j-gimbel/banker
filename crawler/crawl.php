<?php

require_once('simple_html_dom.php');
require_once('config.php');
$url = 'https://www.dkb.de/';

require_once "class.crawler.php";
try {
  // create a log
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
    
    $c = new crawler();
    $c->log = $log;

    $c->doCrawl();
    $c->addNewDataToDB();
}
catch(PDOException $e) {
    // Print PDOException message
    echo "PDO exception in ".$e->getFile()." in ".$e->getLine().": ".$e->getMessage()."\n";
}

catch(Exception $e) {
    echo $e->getMessage()." in file ".$e->getFile()." line ".$e->getLine()."\n";
}



?>