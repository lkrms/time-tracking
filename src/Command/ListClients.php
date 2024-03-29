<?php declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Time\Command\Concept\Command;
use Lkrms\Time\Sync\Entity\Client;
use Salient\Core\Facade\Console;

class ListClients extends Command
{
    public function description(): string
    {
        return 'List clients in ' . $this->TimeEntryProviderName;
    }

    protected function getOptionList(): array
    {
        return [];
    }

    protected function run(string ...$params)
    {
        Console::info("Retrieving clients from {$this->TimeEntryProviderName}");

        $clients = $this->TimeEntryProvider->with(Client::class)->getList();

        /** @var Client $client */
        foreach ($clients as $client) {
            printf(
                "==> %s\n  client_id: %s\n\n",
                $client->Name,
                $client->Id
            );
        }
    }
}
