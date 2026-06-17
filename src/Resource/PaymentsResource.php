<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/** Discount check, saved cards and the 3DS payment entrypoint. */
final class PaymentsResource extends AbstractResource
{
    /**
     * Validate a discount code. Note: this endpoint lives under the `patients` prefix.
     *
     * @return array<array-key, mixed>
     */
    public function checkDiscountCode(
        string $checkType,
        string $discountCode,
        int|string|null $doctorId = null,
        int|string|null $orderId = null,
        int|string|null $specialServiceId = null,
        ?string $programSlug = null,
    ): array {
        $body = ['checkType' => $checkType, 'discountCode' => $discountCode];
        if ($doctorId !== null) {
            $body['doctorId'] = $doctorId;
        }
        if ($orderId !== null) {
            $body['orderId'] = $orderId;
        }
        if ($specialServiceId !== null) {
            $body['specialServiceId'] = $specialServiceId;
        }
        if ($programSlug !== null) {
            $body['programSlug'] = $programSlug;
        }

        return $this->asArray($this->http->request('POST', '/patients/checkDiscountCode', 'bearer', $body));
    }

    /** @return array<array-key, mixed> */
    public function getCards(): array
    {
        return $this->asArray($this->http->request('GET', '/payments/getCards', 'bearer'));
    }

    public function saveCard(
        string $cardHolder,
        string $cardNumber,
        string $cardExpMonth,
        string $cardExpYear,
        string $cardCvv,
    ): mixed {
        return $this->http->request('POST', '/payments/saveCard', 'bearer', [
            'cardHolder' => $cardHolder,
            'cardNumber' => $cardNumber,
            'cardExpMonth' => $cardExpMonth,
            'cardExpYear' => $cardExpYear,
            'cardCvv' => $cardCvv,
        ]);
    }

    /**
     * Start an appointment payment. The amount is computed server-side. On a 3DS
     * flow the result carries `payment3DUrl` — a browser URL to open; the SDK does
     * not follow it.
     *
     * @param array{cardHolder: string, cardNumber: string, cardExpMonth: string, cardExpYear: string, cardCvv: string}|null $cardInfo
     *
     * @return array<array-key, mixed>
     */
    public function pay(
        int|string $doctorId,
        string $appointmentDate,
        bool $is3D,
        bool $termsAccept,
        string $appointmentType = 'interview',
        ?array $cardInfo = null,
        int|string|null $cardId = null,
        int $saveCard = 0,
        string $discountCode = '',
        ?string $caseDetail = null,
    ): array {
        $body = [
            'doctorId' => $doctorId,
            'appointmentDate' => $appointmentDate,
            'appointmentType' => $appointmentType,
            'is3D' => $is3D,
            'termsAccept' => $termsAccept,
            'saveCard' => $saveCard,
            'discountCode' => $discountCode,
        ];
        if ($cardId !== null) {
            $body['cardId'] = $cardId;
        }
        if ($cardInfo !== null) {
            $body['cardInfo'] = $cardInfo;
        }
        if ($caseDetail !== null) {
            $body['caseDetail'] = $caseDetail;
        }

        return $this->asArray($this->http->request('POST', '/payments/interviewPayment', 'bearer', $body));
    }

    public function deleteCard(int|string $cardId): mixed
    {
        return $this->http->request('DELETE', "/payments/deleteCard/{$cardId}", 'bearer');
    }
}
