<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/** Diet lists — the patient's dietitian-written "Diyet Listesi" (JSON only, no PDF export). */
final class DietsResource extends AbstractResource
{
    /**
     * The patient's diet lists (paginated). `$page` defaults to 1 server-side
     * when omitted; page size is fixed to 10.
     */
    public function list(int|string|null $page = null): mixed
    {
        $path = $page !== null
            ? "/patients/dietLists/{$page}"
            : '/patients/dietLists';

        return $this->http->request('GET', $path, 'bearer');
    }

    /** One diet list's detail (an array of meal-time groups). `$listId` = a `list_id` from a `list` item. */
    public function detail(int|string $listId): mixed
    {
        return $this->http->request('GET', "/patients/diet/{$listId}", 'bearer');
    }
}
