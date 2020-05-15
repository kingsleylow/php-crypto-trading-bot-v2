<?php

require_once ('../init.php');

if(!file_exists("../logs/".getmypid().".pid.log")){
$filesCls = new Models\BotComponent\FilesWork();
$filesCls->register_pid_to_file("I am WebServer");
$filesCls->addContent("phpBot///WebServer/// Web Server Started.");
}
return false;