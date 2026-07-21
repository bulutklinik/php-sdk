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

    /**
     * The patient's appointments (`{ foundAppointmentsCount, foundAppointments }`).
     * Each item's `event_id` is the id for {@see cancel()}; rows with `event_id` "0"
     * are paid-order/refund entries and are not cancellable. Server paging is
     * disabled, so page 1 (the default) returns the full list.
     */
    public function list(int|string|null $page = null): mixed
    {
        $path = $page !== null ? "/patients/userAppointments/{$page}" : '/patients/userAppointments';

        return $this->http->request('GET', $path, 'bearer');
    }

    /** The patient's active online-slot reservation holds (with a `minute_diff`/`second_diff` countdown). */
    public function reservations(): mixed
    {
        return $this->http->request('GET', '/patients/userReservations', 'bearer');
    }
}
