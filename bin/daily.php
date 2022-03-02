#!/usr/bin/env php
<?php

use Lkrms\Assert;
use Lkrms\Clockify\ClockifyApi;
use Lkrms\Console\Console;
use Lkrms\Env;
use Lkrms\Err;

require (__DIR__ . "/../vendor/autoload.php");

Err::HandleErrors();
Assert::SapiIsCli();
ini_set("memory_limit", "-1");

$appRoot = realpath(__DIR__ . "/..");
Env::Load("$appRoot/.env");

$clockify    = new ClockifyApi();
$workspaceId = Env::Get("clockify_workspace_id");
$workspace   = $clockify->GetWorkspace($workspaceId);

if (!$workspace)
{
    throw new UnexpectedValueException("No workspace with ID ${workspaceId}");
}

Console::Debug("Clockify workspace:", $workspace->Name);
