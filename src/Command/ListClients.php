<?php declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Time\Sync\Entity\Client;
use Salient\Core\Facade\Console;

final class ListClients extends AbstractCommand
{
    public function getDescription(): string
    {
        return "List clients in {$this->TimeEntryProviderName}";
    }

    protected function getOptionList(): iterable
    {
        return [];
    }

    protected function run(string ...$params)
    {
        Console::info("Retrieving clients from {$this->TimeEntryProviderName}");

        $clients = $this->TimeEntryProvider->with(Client::class)->getList();

        $count = 0;
        /** @var Client $client */
        foreach ($clients as $client) {
            printf(
                "==> %s\n  client_id: %s\n\n",
                $client->Name,
                $client->Id,
            );
            $count++;
        }

        Console::info('Clients retrieved:', (string) $count);
    }
}
