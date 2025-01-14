<?php declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Time\Sync\Entity\Invoice;
use Salient\Core\Facade\Console;
use Salient\Utility\Env;
use Salient\Utility\Format;

final class ListInvoices extends AbstractCommand
{
    public function getDescription(): string
    {
        return "List invoices in {$this->InvoiceProviderName}";
    }

    protected function getOptionList(): iterable
    {
        return [];
    }

    protected function run(string ...$params)
    {
        Console::info("Retrieving invoices from {$this->InvoiceProviderName}");

        $query = [
            '$orderby' => 'date desc',
            '!status' => 'DELETED',
        ];
        if ($prefix = Env::get('invoice_number_prefix', null)) {
            $query['number'] = "{$prefix}*";
        }
        $invoices = $this->InvoiceProvider->with(Invoice::class)->getList($query);

        $count = 0;
        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            printf(
                "==> %s for \$%.2f\n  date: %s\n  client: %s\n\n",
                $invoice->Number,
                $invoice->Total,
                Format::date($invoice->Date),
                $invoice->Client->Name ?? '<unknown>',
            );
            $count++;
        }

        Console::info('Invoices retrieved:', (string) $count);
    }
}
