<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/**
 * The appointment lifecycle.
 *
 * The patient is supplied inline as `$user` — there is no patient login in this
 * mode. The server materialises the patient inside your company on write.
 *
 * **Payment is not taken through the API.** `reserve()` returns a process that is
 * settled through the hosted web checkout; see the reservation response.
 *
 * `$user` shape (write form):
 * `['name' => string, 'surname' => string, 'phoneNumber' => string,
 *   'identityNumber' => ?string, 'email' => ?string, 'birthdate' => ?string,
 *   'nationality' => ?string, 'price' => ?float]`
 */
final class AppointmentsResource extends AbstractResource
{
    /**
     * Hold an online slot for the given patient.
     *
     * @param array<string, mixed> $user
     */
    public function reserve(int|string $slotId, int|string $doctorId, array $user): mixed
    {
        return $this->http->request('POST', '/outher/reservation', 'partner', [
            'slotId' => $slotId,
            'doctorId' => $doctorId,
            'user' => $user,
        ]);
    }

    /**
     * Same as `reserve()`, for integrations that collect the agreements themselves.
     *
     * @param array<string, mixed> $user
     */
    public function reserveWithoutAgreement(int|string $slotId, int|string $doctorId, array $user): mixed
    {
        return $this->http->request('POST', '/outher/reservationWithoutAgreement', 'partner', [
            'slotId' => $slotId,
            'doctorId' => $doctorId,
            'user' => $user,
        ]);
    }

    /**
     * Instant (no slot) reservation.
     *
     * @param array<string, mixed> $user
     */
    public function instantReserve(array $user): mixed
    {
        return $this->http->request('POST', '/outher/instantReservation', 'partner', ['user' => $user]);
    }

    /** Turn a reservation into a confirmed appointment. */
    public function create(string $hash, int|string $outherProcessId): mixed
    {
        return $this->http->request('POST', '/outher/appointment', 'partner', [
            'hash' => $hash,
            'outherProcessId' => $outherProcessId,
        ]);
    }

    /**
     * Book a free-form time range without going through a slot.
     *
     * @param string               $startDate  `Y-m-d H:i`, today or later
     * @param string               $finishDate `Y-m-d H:i`, after `$startDate`
     * @param array<string, mixed> $user
     */
    public function createWithoutSlot(
        int|string $doctorId,
        string $startDate,
        string $finishDate,
        array $user,
        ?int $isOutherDoctor = null,
    ): mixed {
        return $this->http->request('POST', '/outher/appointmentWithoutSlot', 'partner', [
            'doctorId' => $doctorId,
            'startDate' => $startDate,
            'finishDate' => $finishDate,
            'isOutherDoctor' => $isOutherDoctor,
            'user' => $user,
        ]);
    }

    /**
     * Cancel an appointment created with `createWithoutSlot()`.
     *
     * Address it either by process (`hash` + `outherProcessId`) or by coordinates
     * (`doctorId` + `appointmentDate` + `isOutherDoctor`).
     *
     * @param array<string, mixed> $lookup
     */
    public function cancelWithoutSlot(array $lookup): mixed
    {
        return $this->http->request('DELETE', '/outher/appointmentWithoutSlot', 'partner', $lookup);
    }

    /** Appointments you created for the given phone number. */
    public function list(string $phoneNumber, int|string|null $page = null, ?string $type = null): mixed
    {
        return $this->http->request('POST', '/outher/appointments', 'partner', [
            'phoneNumber' => $phoneNumber,
            'page' => $page,
            'type' => $type,
        ]);
    }

    /**
     * A single appointment, addressed by process or by coordinates.
     *
     * @param array<string, mixed> $lookup
     */
    public function info(array $lookup): mixed
    {
        return $this->http->request('POST', '/outher/appointmentInfo', 'partner', $lookup);
    }

    /** Whether a doctor is bookable through your integration. */
    public function checkDoctor(int|string $doctorId, int $isOutherDoctor): mixed
    {
        return $this->http->request('POST', '/outher/checkDoctor', 'partner', [
            'doctorId' => $doctorId,
            'isOutherDoctor' => $isOutherDoctor,
        ]);
    }
}
