<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/** Online reservation, physical appointment and cancellation. */
final class AppointmentsResource extends AbstractResource
{
    /** Reserve an online (interview) slot. Returns null on success. */
    public function reserveInterview(int|string $doctorId, string $appointmentDate, string $appointmentType = 'interview'): mixed
    {
        return $this->http->request('POST', '/patients/addInterviewDateReservation', 'bearer', [
            'doctorId' => $doctorId,
            'appointmentDate' => $appointmentDate,
            'appointmentType' => $appointmentType,
        ]);
    }

    /** Create a physical appointment. */
    public function addPhysical(int|string $doctorId, string $appointmentDate): mixed
    {
        return $this->http->request('POST', '/patients/addNewAppointment', 'bearer', [
            'doctorId' => $doctorId,
            'appointmentDate' => $appointmentDate,
        ]);
    }

    /** Cancel an appointment by event id (`cln_events.id`). */
    public function cancel(int|string $eventId): mixed
    {
        return $this->http->request('DELETE', "/patients/deleteUserAppointment/{$eventId}", 'bearer');
    }
}
