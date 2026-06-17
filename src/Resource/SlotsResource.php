<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/** Doctor availability (materialized slots). */
final class SlotsResource extends AbstractResource
{
    /**
     * Fetch a doctor's free slots — returns a date-keyed map of slots. Build the
     * next step's `appointmentDate` as `"<date> <slotStart>"` (drop the seconds).
     *
     * @return array<array-key, mixed>
     */
    public function schedule(
        int|string $doctorId,
        string $listType,
        ?string $scheduleDate = null,
        int|string $scheduleStep = 7,
        int|string $schedulePage = 1,
    ): array {
        return $this->asArray($this->http->request('POST', '/patients/doctorScheduler', 'bearer', [
            'doctorId' => $doctorId,
            'scheduleDate' => $scheduleDate,
            'scheduleStep' => $scheduleStep,
            'schedulePage' => $schedulePage,
            'listType' => $listType,
        ]));
    }
}
