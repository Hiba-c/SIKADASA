<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use IntlCalendar;
use RuntimeException;

class HijriCalendarService
{
    private const CALENDAR_LOCALE = 'en_US@calendar=islamic-umalqura';

    /**
     * @return array{hijri_year: int, payout_date: string, eid_date: string}
     */
    public function nextPayout(): array
    {
        $calendar = $this->newCalendar();
        $calendar->setTime((float) now(config('app.timezone'))->getTimestamp() * 1000);
        $hijriYear = (int) $calendar->get(IntlCalendar::FIELD_YEAR);
        $payoutDate = $this->payoutDateForYear($hijriYear);

        if ($payoutDate->lt(now(config('app.timezone'))->startOfDay())) {
            $hijriYear++;
            $payoutDate = $this->payoutDateForYear($hijriYear);
        }

        return [
            'hijri_year' => $hijriYear,
            'payout_date' => $payoutDate->toDateString(),
            'eid_date' => $payoutDate->addDays(10)->toDateString(),
        ];
    }

    public function payoutDateForYear(int $hijriYear): CarbonImmutable
    {
        $calendar = $this->newCalendar();
        $calendar->clear();
        $calendar->set(IntlCalendar::FIELD_YEAR, $hijriYear);
        $calendar->set(IntlCalendar::FIELD_MONTH, 9);
        $calendar->set(IntlCalendar::FIELD_DAY_OF_MONTH, 1);
        $calendar->set(IntlCalendar::FIELD_HOUR_OF_DAY, 12);
        $calendar->add(IntlCalendar::FIELD_DAY_OF_MONTH, -10);

        $timestamp = $calendar->getTime();
        if ($timestamp === false) {
            throw new RuntimeException('Tanggal H-10 Hijriah tidak dapat dihitung oleh ICU.');
        }

        return CarbonImmutable::createFromTimestamp(
            (int) floor($timestamp / 1000),
            config('app.timezone'),
        )->startOfDay();
    }

    private function newCalendar(): IntlCalendar
    {
        if (! extension_loaded('intl')) {
            throw new RuntimeException('Ekstensi PHP intl diperlukan untuk menghitung tanggal Umm al-Qura.');
        }

        $calendar = IntlCalendar::createInstance(config('app.timezone'), self::CALENDAR_LOCALE);
        if (! $calendar instanceof IntlCalendar) {
            throw new RuntimeException('Kalender Umm al-Qura tidak tersedia pada versi ICU ini.');
        }

        return $calendar;
    }
}
