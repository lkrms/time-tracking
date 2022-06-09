# time-tracking

Time entry automation.

## Description

This project uses time entry services like [Clockify][clockify] to automate
tasks like creating invoices for billable time in services like [Xero][xero].

It is written in PHP and relies heavily on [`lkrms/util`][lkrms/util].

## Installation

`lkrms/time-tracking` is in early development, so no releases are available yet.

To use it anyway (at your own risk, of course):

1. Clone or download this repository
2. Run `composer install` in the root directory
3. Copy `.env.example` to `.env` and customise

Then, run `./bin/lk-time help` for next steps.

[clockify]: https://clockify.me
[lkrms/util]: https://github.com/lkrms/util-php
[xero]: https://www.xero.com
