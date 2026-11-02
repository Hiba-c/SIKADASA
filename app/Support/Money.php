<?php

namespace App\Support;

final class Money
{
    public static function cents(string|int|float|null $amount): int
    {
        $amount = (string) ($amount ?? '0');
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $amount, $parts)) {
            throw new \InvalidArgumentException('Invalid monetary value.');
        }

        $cents = ((int) $parts[2] * 100) + (int) str_pad($parts[3] ?? '', 2, '0');

        return ($parts[1] ?? '') === '-' ? -$cents : $cents;
    }

    public static function decimal(int $cents): string
    {
        $absolute = abs($cents);
        $sign = $cents < 0 ? '-' : '';

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function percentageOf(int $amountCents, string|int|float $ratePercent): int
    {
        $rateCents = self::cents($ratePercent);
        $whole = intdiv($amountCents, 10000) * $rateCents;
        $remainder = intdiv(($amountCents % 10000) * $rateCents + 5000, 10000);

        return $whole + $remainder;
    }
}
