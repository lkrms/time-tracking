#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\Time\Sync\Entity\Client;
use Lkrms\Time\Sync\Entity\Invoice;
use Lkrms\Time\Sync\Entity\InvoiceLineItem;
use Lkrms\Time\Sync\Entity\Project;
use Lkrms\Time\Sync\Entity\Task;
use Lkrms\Time\Sync\Entity\Tenant;
use Lkrms\Time\Sync\Entity\TimeEntry;
use Lkrms\Time\Sync\Entity\User;
use Salient\Cli\CliApplication;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Core\Facade\Console;
use Salient\Sli\Command\Generate\AbstractGenerateCommand;
use Salient\Sli\Command\Generate\GenerateSyncEntity;
use Salient\Sli\Command\Generate\GenerateSyncProvider;
use Salient\Sli\EnvVar;
use Salient\Utility\Arr;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Json;
use Salient\Utility\Package;
use Salient\Utility\Reflect;
use Salient\Utility\Regex;

$dir = dirname(__DIR__);
$loader = require "$dir/vendor/autoload.php";

// Entity => [ name, provider|null, endpoint|null, ...args ]
$entities = [
    Client::class => ['clients', null, null],
    Invoice::class => ['invoices', null, null, '--one', 'Client=Client', '--many', 'LineItems=InvoiceLineItem'],
    InvoiceLineItem::class => ['line_items', null, null, '--trim', 'InvoiceLineItem,LineItem'],
    Project::class => ['projects', null, null, '--one', 'Client=Client', '--many', 'Tasks=Task'],
    Task::class => ['tasks', null, null, '--one', 'Project=Project'],
    Tenant::class => ['workspaces', null, null, '--many', 'Users=User'],
    TimeEntry::class => ['time_entries', null, null, '--one', 'User=User,Task=Task,Project=Project'],
    User::class => ['users', null, null, '--one', 'ActiveTenant=Tenant'],
];

$providers = [
    Client::class => ['--op', 'get,get-list'],
    Invoice::class => ['--op', 'create,get,get-list'],
    Project::class => ['--op', 'get,get-list'],
    Task::class => ['--op', 'get,get-list'],
    Tenant::class => ['--op', 'get,get-list'],
    TimeEntry::class => ['--op', 'get,update,get-list'],
    User::class => ['--op', 'get,get-list'],
];

$app = new CliApplication($dir);
$generateEntity = new GenerateSyncEntity($app);
$generateProvider = new GenerateSyncProvider($app);

/** @var string $name */
foreach (Reflect::getConstants(EnvVar::class) as $name) {
    Env::unset($name);
}

/** @var string[] */
$args = $_SERVER['argv'];
$args = [
    '--collapse',
    '--force',
    ...array_slice($args, 1),
];

/**
 * @param AbstractGenerateCommand|string $commandOrFile
 */
function generated($commandOrFile): void
{
    global $generated;

    $file = $commandOrFile instanceof AbstractGenerateCommand
        ? $commandOrFile->OutputFile
        : $commandOrFile;

    if ($file === null) {
        throw new LogicException('No file generated');
    }

    $generated[] = '/' . File::getRelativePath($file, Package::path());
}

$status = 0;
$generated = [];

foreach ($entities as $class => $entityArgs) {
    $entity = array_shift($entityArgs);
    $provider = array_shift($entityArgs);
    $endpoint = array_shift($entityArgs);
    $file = "$dir/resources/data/entity/{$entity}.json";
    $save = false;
    if (is_file($file)) {
        array_unshift($entityArgs, '--json', $file);
        // @phpstan-ignore notIdentical.alwaysFalse
        if ($provider !== null) {
            generated($file);
        }
        // @phpstan-ignore booleanOr.alwaysTrue, identical.alwaysTrue, identical.alwaysTrue
    } elseif ($provider === null || $endpoint === null) {
        throw new LogicException(sprintf('File not found: %s', $file));
    } else {
        array_unshift($entityArgs, '--provider', $provider, '--endpoint', $endpoint);
        $save = true;
    }
    $status |= $generateEntity(...[...$args, ...$entityArgs, $class]);
    generated($generateEntity);
    // @phpstan-ignore booleanAnd.leftAlwaysFalse
    if ($save && $generateEntity->Entity !== null) {
        File::createDir(dirname($file));
        File::writeContents($file, Json::prettyPrint($generateEntity->Entity));
        generated($file);
    }
}

foreach ($providers as $class => $providerArgs) {
    $status |= $generateProvider(...[...$args, ...$providerArgs, $class]);
    generated($generateProvider);
}

$file = "$dir/.gitattributes";
$attributes = Regex::grep(
    '/(^#| linguist-generated$)/',
    Arr::trim(File::getLines($file)),
    \PREG_GREP_INVERT
);
// @phpstan-ignore foreach.emptyArray
foreach ($generated as $generated) {
    $attributes[] = sprintf('%s linguist-generated', $generated);
}
sort($attributes);
$attributes = implode(\PHP_EOL, $attributes) . \PHP_EOL;
if (File::getContents($file) !== $attributes) {
    if (in_array('--check', $args)) {
        Console::info('Would replace', $file);
        Console::count(Level::ERROR);
        $status |= 1;
    } else {
        Console::info('Replacing', $file);
        File::writeContents($file, $attributes);
    }
} else {
    Console::log('Nothing to do:', $file);
}

Console::summary('Code generation completed');

exit($status);
