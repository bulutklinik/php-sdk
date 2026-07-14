<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/** "Cildimde Neyim Var" — AI skin-lesion analysis. */
final class SkinResource extends AbstractResource
{
    /**
     * Analyze one or more skin photos. Each image is classified (lesion `label`),
     * summarized in Turkish (`comment`), and returned with quality flags, a
     * `confidence`, possible ICD hints and an opaque `case_detail` blob — which may
     * be forwarded verbatim as a payment's `caseDetail`.
     *
     * @param list<array{image: string, branch_id?: int}> $images base64 image + optional branch id
     */
    public function analyze(array $images): mixed
    {
        return $this->http->request('POST', '/patients/imageCheck', 'bearer', ['images' => $images]);
    }
}
