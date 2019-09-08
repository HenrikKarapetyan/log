<?php

use henrik\log\adapters\FileLoggerAdapter;
use henrik\log\Logger;

require "../vendor/autoload.php";


$logger = new Logger();
$fileLoggerAdapter = new FileLoggerAdapter();
$fileLoggerAdapter->setDirectory("../logs");
$logger->setLogger($fileLoggerAdapter);

$logger->info("sdfsdfsdfsdf");