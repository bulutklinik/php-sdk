<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/** AI meal-photo calorie/nutrition estimation (sibling of `skin`). */
final class MealsResource extends AbstractResource
{
    /**
     * Estimate calories/nutrition from a meal photo. Idiomatic parameters map to
     * the API's snake_case body (`portion_size`, `portion_grams`, `meal_type`).
     *
     * @param string      $image        base64 image (a `data:…;base64,` prefix is allowed)
     * @param string      $portionSize  `small` | `medium` | `large` | `custom`
     * @param string      $mealType     `breakfast` | `lunch` | `dinner` | `snack`
     * @param int|null    $portionGrams required when `$portionSize` is `custom`
     * @param string|null $note         optional Turkish note (≤1000 chars)
     */
    public function analyze(
        string $image,
        string $portionSize,
        string $mealType,
        ?int $portionGrams = null,
        ?string $note = null,
    ): mixed {
        $body = [
            'image' => $image,
            'portion_size' => $portionSize,
            'meal_type' => $mealType,
        ];
        if ($portionGrams !== null) {
            $body['portion_grams'] = $portionGrams;
        }
        if ($note !== null) {
            $body['note'] = $note;
        }

        return $this->http->request('POST', '/patients/imageAnalyzeMeal', 'bearer', $body);
    }
}
