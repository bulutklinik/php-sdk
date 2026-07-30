<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/** Doctor availability. */
final class SlotsResource extends AbstractResource
{
    /**
     * Bookable slots for a doctor. Either pass `$scheduleDate`, or page through
     * with `$scheduleStep` + `$schedulePage`; the server requires one of the two
     * forms.
     *
     * @param string|null $scheduleDate `Y-m-d`
     */
    public function schedule(
        int|string $doctorId,
        ?string $scheduleDate = null,
        ?int $scheduleStep = null,
        ?int $schedulePage = null,
    ): mixed {
        return $this->http->request('POST', '/outher/doctorSlots', 'partner', [
            'doctorId' => $doctorId,
            'scheduleDate' => $scheduleDate,
            'scheduleStep' => $scheduleStep,
            'schedulePage' => $schedulePage,
        ]);
    }
}
