<?php

namespace Lkrms\Time\Command;

use DateTime;
use Lkrms\Console\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use Lkrms\Time\Concept\Command;
use Lkrms\Time\Entity\Invoice;
use Lkrms\Time\Entity\InvoiceLineItem;
use Lkrms\Time\Entity\TimeEntry;
use Lkrms\Time\Support\TimeEntryCollection;

class GenerateInvoices extends Command
{
    protected function _getDescription(): string
    {
        return "Create invoices for unbilled time entries";
    }

    protected function _getOptions(): array
    {
        return $this->getTimeEntryOptions("Create an invoice", false, true, true);
    }

    protected function run(string ...$params)
    {
        if (!$this->getOptionValue("force"))
        {
            Env::dryRun(true);
        }

        Console::info("Retrieving unbilled time from", $this->TimeEntryProviderName);

        $times = $this->getTimeEntries(true, false);

        /** @var TimeEntryCollection[] */
        $clientTimes    = [];
        $clientNames    = [];
        $timeEntryCount = 0;

        foreach ($times as $time)
        {
            $clientId  = $time->Project->Client->Id;
            $entries   = $clientTimes[$clientId] ?? ($clientTimes[$clientId] = $this->app()->get(TimeEntryCollection::class));
            $entries[] = $time;
            $clientNames[$clientId] = $time->Project->Client->Name;
            $timeEntryCount++;
        }

        if (!$timeEntryCount)
        {
            Console::info("No uninvoiced time entries");
            return;
        }

        Console::info("Retrieving clients from", $this->InvoiceProviderName);
        $invClients = Convert::listToMap(
            iterator_to_array($this->InvoiceProvider->getClients(["name" => $clientNames])),
            "Name"
        );

        Console::log("Preparing " . Convert::numberToNoun(count($clientTimes), "client invoice", null, true)
            . " for " . Convert::numberToNoun($timeEntryCount, "time entry", "time entries", true));

        $showMap = [
            "date"        => TimeEntry::DATE,
            "time"        => TimeEntry::TIME,
            "project"     => TimeEntry::PROJECT,
            "task"        => TimeEntry::TASK,
            "user"        => TimeEntry::USER,
            "description" => TimeEntry::DESCRIPTION,
        ];
        $show = array_reduce(
            $this->getOptionValue("hide"),
            fn($prev, $value) => $prev & ~$showMap[$value],
            TimeEntry::ALL
        );

        $next = null;

        if ($prefix = Env::get("invoice_number_prefix", null))
        {
            $next = (int)Env::get("invoice_number_next", "1");

            $invoices = $this->InvoiceProvider->getInvoices([
                "number"   => "{$prefix}*",
                '$orderby' => "date desc"
            ]);

            $seen = 0;
            foreach ($invoices as $invoice)
            {
                $next = max((int)substr($invoice->Number, strlen($prefix)) + 1, $next, 1);
                if ($seen++ == 99)
                {
                    break;
                }
            }

            unset($invoices);
        }

        File::maybeCreateDirectory($tempDir = implode("/", [
            $this->app()->TempPath,
            Convert::classToBasename(self::class),
            $this->InvoiceProviderName . "-" . $this->InvoiceProvider->getBackendHash()
        ]));

        foreach ($clientTimes as $clientId => $entries)
        {
            $name    = $clientNames[$clientId];
            $summary = $this->getBillableSummary($entries->BillableAmount, $entries->BillableHours);

            if (!($invClient = $invClients[$name] ?? null))
            {
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

            if (Env::dryRun())
            {
                foreach ($entries as $entry)
                {
                    printf("==> \$%.2f (%.2f hours):\n  %s\n\n",
                        $entry->getBillableAmount(),
                        $entry->getBillableHours(),
                        str_replace("\n", "\n  ", $entry->Description));
                }
                continue;
            }

            $markInvoiced = [];

            /** @var Invoice */
            $invoice            = $this->app()->get(Invoice::class);
            $invoice->Number    = $next ? $prefix . ($next++) : null;
            $invoice->Date      = new DateTime("today");
            $invoice->DueDate   = new DateTime("today +7 days");
            $invoice->Client    = $invClient;
            $invoice->LineItems = [];

            foreach ($entries as $entry)
            {
                /** @var InvoiceLineItem */
                $item = $invoice->LineItems[] = $this->app()->get(InvoiceLineItem::class);
                $item->Description = $entry->Description;
                $item->Quantity    = $entry->getBillableHours();
                $item->UnitAmount  = $entry->BillableRate;
                $item->ItemCode    = Env::get("invoice_item_code", "") ?: null;
                $item->AccountCode = Env::get("invoice_account_code", "") ?: null;

                array_push($markInvoiced, ...($entry->getMerged() ?: [$entry]));
            }

            $invoice = $this->InvoiceProvider->createInvoice($invoice);
            Console::log("Invoice created in {$this->InvoiceProviderName}:",
                sprintf(
                    "%s for %s (%.2f + %.2f tax = %s %.2f)",
                    $invoice->Number,
                    $invoice->Client->Name,
                    $invoice->SubTotal,
                    $invoice->TotalTax,
                    $invoice->Currency,
                    $invoice->Total
                ));

            // TODO: something better with this data
            file_put_contents($tempDir . "/{$invoice->Number}.json", json_encode($invoice));
            file_put_contents($tempDir . "/{$invoice->Number}-timeEntries.json", json_encode($markInvoiced));

            Console::info("Marking " . Convert::numberToNoun(
                count($markInvoiced), "time entry", "time entries", true
            ) . " as invoiced in", $this->TimeEntryProviderName);
            $this->TimeEntryProvider->markTimeEntriesInvoiced($markInvoiced);
        }
    }
}
