#!/usr/bin/env php
<?php

use Lkrms\Assert;
use Lkrms\Clockify\ClockifyApi;
use Lkrms\Env;
use Lkrms\Err;

require (__DIR__ . "/../vendor/autoload.php");

Err::HandleErrors();
Assert::SapiIsCli();
ini_set("memory_limit", "-1");

$appRoot = realpath(__DIR__ . "/..");
Env::Load("$appRoot/.env");

$clockify    = new ClockifyApi();
$user        = $clockify->getUser();
$timeEntries = $clockify->getTimeEntries($user);

echo json_encode($timeEntries);
