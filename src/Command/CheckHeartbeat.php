<?php

declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Time\Concept\Command;

class CheckHeartbeat extends Command
{
    protected function _getDescription(): string
    {
        return "Send a heartbeat request to " . implode(" and ", $this->UniqueProviderNames);
    }

    protected function _getOptions(): array
    {
        return [
            CliOption::getBuilder()
            ->long("ttl")
            ->short("t")
            ->valueName("SECONDS")
            ->description("The time-to-live of a positive result")
            ->optionType(CliOptionType::VALUE)
            ->defaultValue("300")
            ->go(),
        ];
    }

    protected function run(string ...$params)
    {
        foreach ($this->UniqueProviders as $provider)
        {
            $provider->checkHeartbeat((int)$this->getOptionValue("ttl"));
        }
    }
}
