<?php

declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Console\Console;
use Lkrms\Time\Concept\Command;

class ListClients extends Command
{
    protected function _getDescription(): string
    {
        return "List clients in " . $this->TimeEntryProviderName;
    }

    protected function _getOptions(): array
    {
        return [];
    }

    protected function run(string ...$params)
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
