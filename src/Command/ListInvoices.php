<?php

declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Facade\Console;
use Lkrms\Facade\Env;
use Lkrms\Facade\Format;
use Lkrms\Time\Concept\Command;
use Lkrms\Time\Entity\Invoice;

class ListInvoices extends Command
{
    public function getShortDescription(): string
    {
        return "List invoices in " . $this->InvoiceProviderName;
    }

    protected function getOptionList(): array
    {
        return [];
    }

    protected function run(string ...$params)
    {
        Console::info("Retrieving invoices from", $this->InvoiceProviderName);

        $query = [
            '$orderby' => "date desc",
            "!status"  => "DELETED",
        ];
        if ($prefix = Env::get("invoice_number_prefix", null))
        {
            $query["number"] = "{$prefix}*";
        }
        $invoices = $this->InvoiceProvider->with(Invoice::class)->getList($query);

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice)
        {
            printf(
                "==> %s for \$%.2f\n  date: %s\n  client: %s\n\n",
                $invoice->Number,
                $invoice->Total,
                Format::date($invoice->Date),
                $invoice->Client->Name,
            );
        }
    }
}
