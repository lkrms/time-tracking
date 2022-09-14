<?php

declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Console\Console;
use Lkrms\Facade\Env;
use Lkrms\Time\Concept\Command;

class ListInvoices extends Command
{
    protected function _getDescription(): string
    {
        return "List invoices in " . $this->InvoiceProviderName;
    }

    protected function _getOptions(): array
    {
        return [];
    }

    protected function run(string ...$params)
    {
        Console::info("Retrieving invoices from", $this->InvoiceProviderName);

        $query = [
            '$orderby' => "date desc",
        ];
        if ($prefix = Env::get("invoice_number_prefix", null))
        {
            $query["number"] = "{$prefix}*";
        }
        $invoices = $this->InvoiceProvider->getInvoices($query);

        foreach ($invoices as $invoice)
        {
            printf(
                "==> %s for \$%.2f\n  date: %s\n  client: %s\n\n",
                $invoice->Number,
                $invoice->Total,
                $invoice->Date,
                $invoice->Client->Name,
            );
        }
    }
}
