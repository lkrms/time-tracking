<?php declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Time\Command\Concept\Command;
use Lkrms\Time\Support\TimeEntryCollection;
use Lkrms\Time\Sync\Entity\Client;
use Lkrms\Time\Sync\Entity\Invoice;
use Lkrms\Time\Sync\Entity\InvoiceLineItem;
use Lkrms\Time\Sync\TimeEntity\TimeEntry;
use Salient\Cli\CliOption;
use Salient\Core\Exception\UnexpectedValueException;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Env;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Inflect;
use DateTimeImmutable;

class GenerateInvoices extends Command
{
    protected ?bool $NoMarkInvoiced;

    public function description(): string
    {
        return 'Create invoices for unbilled time entries';
    }

    protected function getOptionList(): array
    {
        return $this->getTimeEntryOptions(
            'Create an invoice',
            false,
            true,
            true,
            false,
            false,
            [
                CliOption::build()
                    ->long('no-mark-invoiced')
                    ->short('u')
                    ->description('Do not mark time entries as invoiced')
                    ->bindTo($this->NoMarkInvoiced),
            ]
        );
    }

    protected function run(string ...$params)
    {
        if (!$this->Force) {
            Env::dryRun(true);
        }

        Console::info("Retrieving unbilled time from {$this->TimeEntryProviderName}");

        $times = $this->getTimeEntries(true, false);

        /** @var TimeEntryCollection[] */
        $clientTimes = [];
        $clientNames = [];
        $timeEntryCount = 0;

        foreach ($times as $time) {
            $clientId = $time->Project->Client->Id ?? null;
            $clientName = $time->Project->Client->Name ?? null;
            if ($clientId === null) {
                Console::warn('Skipping time entry with no client:', $time->getSummary());
                continue;
            }
            if ($clientName === null) {
                throw new UnexpectedValueException(sprintf(
                    'Client has no name: %s',
                    $clientId,
                ));
            }
            $entries = $clientTimes[$clientId]
                ??= $this->App->get(TimeEntryCollection::class);
            $entries[] = $time;
            $clientNames[$clientId] = $clientName;
            $timeEntryCount++;
        }

        if (!$timeEntryCount) {
            Console::info('No unbilled time entries');

            return;
        }

        Console::info("Retrieving clients from {$this->InvoiceProviderName}");
        $invClients = Arr::toMap(
            $this->InvoiceProvider
                 ->with(Client::class)
                 ->getListA(['name' => $clientNames]),
            'Name'
        );

        Console::log(Inflect::format(
            $clientTimes,
            'Preparing {{#}} client {{#:invoice}} for %s',
            Inflect::format($timeEntryCount, '{{#}} time {{#:entry}}'),
        ));

        $show = $this->getTimeEntryMask();
        $next = null;

        $prefix = Env::getNullable('invoice_number_prefix', null);
        if ($prefix !== null) {
            $next = Env::getInt('invoice_number_next', 1);

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
                $next = max((int) substr((string) $invoice->Number, strlen($prefix)) + 1, $next, 1);
                if ($seen++ === 99) {
                    break;
                }
            }

            unset($invoices);
        }

        $tempDir = implode('/', [
            $this->App->getTempPath(),
            Get::basename(self::class),
            $this->InvoiceProviderName . '-' . $this->InvoiceProvider->getProviderId()
        ]);
        File::createDir($tempDir);

        $invoices = 0;
        $billableAmount = 0;
        $billableHours = 0;

        /** @var array<string,float> */
        $subTotal = [];
        /** @var array<string,float> */
        $totalTax = [];
        /** @var array<string,float> */
        $total = [];

        foreach ($clientTimes as $clientId => $entries) {
            $name = $clientNames[$clientId];
            $summary = $this->getBillableSummary($entries->BillableAmount, $entries->BillableHours);

            $invClient = $invClients[$name] ?? null;
            if ($invClient === null) {
                Console::error("Skipping $name (not found in {$this->InvoiceProviderName}):", $summary);
                continue;
            }
            Console::info("Invoicing $name:", $summary);

            $invoices++;
            $billableAmount += $entries->BillableAmount;
            $billableHours += $entries->BillableHours;

            $entries = $entries->groupBy(
                $show,
                // Even if they're excluded from the invoice, don't merge
                // entries with different project IDs and/or billable rates
                fn(TimeEntry $t) => [$t->Project->Id ?? null, $t->BillableRate]
            );

            if (Env::dryRun()) {
                foreach ($entries as $entry) {
                    printf(
                        "==> \$%.2f (%.2f hours):\n  %s\n\n",
                        $entry->getBillableAmount(),
                        $entry->getBillableHours(),
                        str_replace("\n", "\n  ", (string) $entry->Description)
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
                $item->ItemCode = Env::getNullable('invoice_item_code', null);
                $item->AccountCode = Env::getNullable('invoice_account_code', null);
                $invoice->LineItems[] = $item;

                array_push($markInvoiced, ...($entry->getMerged() ?: [$entry]));
            }

            $invoice = $this->InvoiceProvider->with(Invoice::class)->create($invoice);
            Console::log(
                "Invoice created in {$this->InvoiceProviderName}:",
                sprintf(
                    '%s for %s (%.2f + %.2f tax = %s %.2f)',
                    $invoice->Number,
                    $invoice->Client->Name ?? '<unknown>',
                    $invoice->SubTotal,
                    $invoice->TotalTax,
                    $invoice->Currency,
                    $invoice->Total,
                )
            );

            $currency = $invoice->Currency;
            $subTotal[$currency] = ($subTotal[$currency] ?? 0.0) + $invoice->SubTotal;
            $totalTax[$currency] = ($totalTax[$currency] ?? 0.0) + $invoice->TotalTax;
            $total[$currency] = ($total[$currency] ?? 0.0) + $invoice->Total;

            // TODO: something better with this data
            file_put_contents($tempDir . "/{$invoice->Number}.json", json_encode($invoice));
            file_put_contents($tempDir . "/{$invoice->Number}-timeEntries.json", json_encode($markInvoiced));

            $count = Inflect::format($markInvoiced, '{{#}} time {{#:entry}}');

            if ($this->NoMarkInvoiced) {
                Console::error("Not marking $count as invoiced in {$this->TimeEntryProviderName}", null, null, false);
                continue;
            }

            Console::info("Marking $count as invoiced in {$this->TimeEntryProviderName}");
            $this->TimeEntryProvider->markTimeEntriesInvoiced($markInvoiced);
        }

        $count = Inflect::format($invoices, '{{#}} {{#:invoice}}');

        if (Env::dryRun()) {
            Console::info(
                "$count would be created in {$this->InvoiceProviderName}:",
                $this->getBillableSummary($billableAmount, $billableHours),
            );
            return;
        }

        if (!$total) {
            Console::summary('Invoice run completed');
            return;
        }

        $totals = [];
        foreach ($total as $currency => $currencyTotal) {
            $totals[] = sprintf(
                '%.2f + %.2f tax = %s %.2f',
                $subTotal[$currency],
                $totalTax[$currency],
                $currency,
                $currencyTotal,
            );
        }

        Console::summary(sprintf(
            '%s created in %s (%s)',
            $count,
            $this->InvoiceProviderName,
            implode(', ', $totals),
        ), '');
    }
}
