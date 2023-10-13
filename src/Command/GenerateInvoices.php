<?php declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Facade\Console;
use Lkrms\Facade\File;
use Lkrms\Time\Command\Concept\Command;
use Lkrms\Time\Entity\Client;
use Lkrms\Time\Entity\Invoice;
use Lkrms\Time\Entity\InvoiceLineItem;
use Lkrms\Time\Entity\TimeEntry;
use Lkrms\Time\Support\TimeEntryCollection;
use Lkrms\Utility\Convert;
use DateTimeImmutable;

class GenerateInvoices extends Command
{
    public function description(): string
    {
        return 'Create invoices for unbilled time entries';
    }

    protected function getOptionList(): array
    {
        return $this->getTimeEntryOptions('Create an invoice', false, true, true);
    }

    protected function run(string ...$params)
    {
        if (!$this->Force) {
            $this->Env->dryRun(true);
        }

        Console::info('Retrieving unbilled time from', $this->TimeEntryProviderName);

        $times = $this->getTimeEntries(true, false);

        /** @var TimeEntryCollection[] */
        $clientTimes = [];
        $clientNames = [];
        $timeEntryCount = 0;

        foreach ($times as $time) {
            $clientId = $time->Project->Client->Id;
            $entries = $clientTimes[$clientId]
                ?? ($clientTimes[$clientId] = $this->App->get(TimeEntryCollection::class));
            $entries[] = $time;
            $clientNames[$clientId] = $time->Project->Client->Name;
            $timeEntryCount++;
        }

        if (!$timeEntryCount) {
            Console::info('No unbilled time entries');

            return;
        }

        Console::info('Retrieving clients from', $this->InvoiceProviderName);
        $invClients = Convert::listToMap(
            $this->InvoiceProvider
                 ->with(Client::class)
                 ->getListA(['name' => $clientNames]),
            'Name'
        );

        $count = count($clientTimes);
        Console::log(sprintf(
            'Preparing %d %s for %d %s',
            $count,
            Convert::plural($count, 'client invoice'),
            $timeEntryCount,
            Convert::plural($timeEntryCount, 'time entry', 'time entries'),
        ));

        $show = $this->getTimeEntryMask();
        $next = null;

        $prefix = $this->Env->getNullable('invoice_number_prefix', null);
        if ($prefix !== null) {
            $next = $this->Env->getInt('invoice_number_next', 1);

            /** @var iterable<Invoice> $invoices */
            $invoices =
                $this->InvoiceProvider
                     ->with(Invoice::class)
                     ->getList([
                         'number' => "{$prefix}*",
                         '$orderby' => 'date desc',
                         '!status' => 'DELETED',
                     ]);

            $seen = 0;
            foreach ($invoices as $invoice) {
                $next = max((int) substr($invoice->Number, strlen($prefix)) + 1, $next, 1);
                if ($seen++ === 99) {
                    break;
                }
            }

            unset($invoices);
        }

        $tempDir = implode('/', [
            $this->App->getTempPath(),
            Convert::classToBasename(self::class),
            $this->InvoiceProviderName . '-' . $this->InvoiceProvider->getProviderId()
        ]);
        File::maybeCreateDirectory($tempDir);

        foreach ($clientTimes as $clientId => $entries) {
            $name = $clientNames[$clientId];
            $summary = $this->getBillableSummary($entries->BillableAmount, $entries->BillableHours);

            $invClient = $invClients[$name] ?? null;
            if ($invClient === null) {
                Console::error("Skipping $name (not found in {$this->InvoiceProviderName}):", $summary);
                continue;
            }
            Console::info("Invoicing $name:", $summary);

            $entries = $entries->groupBy(
                $show,
                // Even if they're excluded from the invoice, don't merge
                // entries with different project IDs and/or billable rates
                fn(TimeEntry $t) => [$t->Project->Id ?? null, $t->BillableRate]
            );

            if ($this->Env->dryRun()) {
                foreach ($entries as $entry) {
                    printf(
                        "==> \$%.2f (%.2f hours):\n  %s\n\n",
                        $entry->getBillableAmount(),
                        $entry->getBillableHours(),
                        str_replace("\n", "\n  ", $entry->Description)
                    );
                }
                continue;
            }

            $markInvoiced = [];

            $invoice = $this->App->get(Invoice::class);
            $invoice->Number = $next ? $prefix . ($next++) : null;
            $invoice->Date = new DateTimeImmutable('today');
            $invoice->DueDate = new DateTimeImmutable('today +7 days');
            $invoice->Client = $invClient;
            $invoice->LineItems = [];

            foreach ($entries as $entry) {
                $item = $this->App->get(InvoiceLineItem::class);
                $item->Description = $entry->Description;
                $item->Quantity = $entry->getBillableHours();
                $item->UnitAmount = $entry->BillableRate;
                $item->ItemCode = $this->Env->getNullable('invoice_item_code', null);
                $item->AccountCode = $this->Env->getNullable('invoice_account_code', null);
                $invoice->LineItems[] = $item;

                array_push($markInvoiced, ...($entry->getMerged() ?: [$entry]));
            }

            /** @var Invoice $invoice */
            $invoice = $this->InvoiceProvider->with(Invoice::class)->create($invoice);
            Console::log(
                "Invoice created in {$this->InvoiceProviderName}:",
                sprintf(
                    '%s for %s (%.2f + %.2f tax = %s %.2f)',
                    $invoice->Number,
                    $invoice->Client->Name,
                    $invoice->SubTotal,
                    $invoice->TotalTax,
                    $invoice->Currency,
                    $invoice->Total
                )
            );

            // TODO: something better with this data
            file_put_contents($tempDir . "/{$invoice->Number}.json", json_encode($invoice));
            file_put_contents($tempDir . "/{$invoice->Number}-timeEntries.json", json_encode($markInvoiced));

            Console::info('Marking ' . Convert::plural(
                count($markInvoiced), 'time entry', 'time entries', true
            ) . ' as invoiced in', $this->TimeEntryProviderName);

            $this->TimeEntryProvider->markTimeEntriesInvoiced($markInvoiced);
        }
    }
}
