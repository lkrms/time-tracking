<?php

namespace Lkrms\Time\Command;

use Lkrms\Cli\CliCommand;
use Lkrms\Console\Console;
use Lkrms\Time\Entity\TimeEntryProvider;
use Lkrms\Util\Convert;

class ListClients extends CliCommand
{
    /**
     * @var TimeEntryProvider
     */
    private $TimeEntryProvider;

    /**
     * @var string
     */
    private $TimeEntryProviderName;

    public function __construct(TimeEntryProvider $timeEntryProvider)
    {
        $this->TimeEntryProvider     = $timeEntryProvider;
        $this->TimeEntryProviderName = preg_replace(
            "/Provider$/", "", Convert::classToBasename(get_class($timeEntryProvider))
        );
    }

    protected function _getDescription(): string
    {
        return "List clients in " . $this->TimeEntryProviderName;
    }

    protected function _getOptions(): array
    {
        return [];
    }

    protected function _run(string ...$params)
    {
        Console::info("Retrieving clients from", $this->TimeEntryProviderName);

        $clients = $this->TimeEntryProvider->getClients();

        foreach ($clients as $client)
        {
            printf(
                "==> %s\n  client_id: %s\n\n",
                $client->Name,
                $client->Id
            );
        }
    }
}