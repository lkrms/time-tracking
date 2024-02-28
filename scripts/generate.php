#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\Time\Sync\Entity\Client;
use Lkrms\Time\Sync\Entity\Invoice;
use Lkrms\Time\Sync\Entity\Project;
use Lkrms\Time\Sync\Entity\Task;
use Lkrms\Time\Sync\Entity\Tenant;
use Lkrms\Time\Sync\Entity\TimeEntry;
use Lkrms\Time\Sync\Entity\User;
use Salient\Catalog\Core\MessageLevel as Level;
use Salient\Cli\CliApplication;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Env;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Json;
use Salient\Core\Utility\Package;
use Salient\Core\Utility\Pcre;
use Salient\Sli\Catalog\EnvVar;
use Salient\Sli\Command\Generate\Concept\GenerateCommand;
use Salient\Sli\Command\Generate\GenerateSyncEntity;
use Salient\Sli\Command\Generate\GenerateSyncProvider;

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->addPsr4('Salient\\Sli\\', Package::packagePath('salient/toolkit') . '/src/Sli/');

$entities = [];

$providers = [
    Client::class => ['--op', 'get,get-list'],
    Invoice::class => ['--op', 'create,get,get-list'],
    Project::class => ['--op', 'get,get-list'],
    Task::class => ['--op', 'get,get-list'],
    Tenant::class => ['--op', 'get,get-list'],
    TimeEntry::class => ['--op', 'get,update,get-list'],
    User::class => ['--op', 'get,get-list'],
];

$app = new CliApplication(dirname(__DIR__));
$generateEntity = new GenerateSyncEntity($app);
$generateProvider = new GenerateSyncProvider($app);

$class = new ReflectionClass(EnvVar::class);
foreach ($class->getReflectionConstants() as $constant) {
    if (!$constant->isPublic()) {
        continue;
    }
    Env::unset($constant->getValue());
}

$args = [
    '--force',
    ...array_slice($_SERVER['argv'], 1),
];

/**
 * @param GenerateCommand|string $commandOrFile
 */
function generated($commandOrFile): void
{
    global $generated;

    $file = $commandOrFile instanceof GenerateCommand
        ? $commandOrFile->OutputFile
        : $commandOrFile;

    if ($file === null) {
        throw new LogicException('No file generated');
    }

    $generated[] = '/' . File::relativeToParent($file, Package::path());
}

$status = 0;
$generated = [];

foreach ($entities as $class => $entityArgs) {
    $entity = array_shift($entityArgs);
    $provider = array_shift($entityArgs);
    $endpoint = array_shift($entityArgs);
    $file = dirname(__DIR__) . "/resources/data/entity/{$entity}.json";
    $save = false;
    if (is_file($file)) {
        array_unshift($entityArgs, '--json', $file);
        generated($file);
    } else {
        array_unshift($entityArgs, '--provider', $provider, '--endpoint', $endpoint);
        $save = true;
    }
    $status |= $generateEntity(...[...$args, ...$entityArgs, $class]);
    generated($generateEntity);
    if ($save && $generateEntity->Entity !== null) {
        File::createDir(dirname($file));
        File::putContents($file, Json::prettyPrint($generateEntity->Entity));
        generated($file);
    }
}

foreach ($providers as $class => $providerArgs) {
    $status |= $generateProvider(...[...$args, ...$providerArgs, $class]);
    generated($generateProvider);
}

$file = dirname(__DIR__) . '/.gitattributes';
$attributes = Pcre::grep(
    '/(^#| linguist-generated$)/',
    Arr::trim(file($file)),
    \PREG_GREP_INVERT
);
// @phpstan-ignore-next-line
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
        File::putContents($file, $attributes);
    }
} else {
    Console::log('Nothing to do:', $file);
}

Console::summary('Code generation completed');

exit($status);
