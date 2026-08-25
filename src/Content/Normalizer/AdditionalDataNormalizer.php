<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\Normalizer;

use Alengo\SuluContentExtraBundle\Model\AdditionalDataInterface;
use Sulu\Content\Application\ContentNormalizer\Normalizer\NormalizerInterface;

final class AdditionalDataNormalizer implements NormalizerInterface
{
    public function enhance(object $object, array $normalizedData): array
    {
        if (!$object instanceof AdditionalDataInterface) {
            return $normalizedData;
        }

        $additionalData = $object->getAdditionalData();

        // Emit the individual values flat (the admin form binds its fields to them) AND a
        // stable "additionalData" key that is always present, even when empty. The flat
        // keys let the form round-trip; the always-present "additionalData" key lets the
        // data mapper recognise a content-write that carries additionalData — so restoring
        // a version whose additionalData was empty reliably clears it, exactly like seo/excerpt.
        return \array_merge($normalizedData, $additionalData, ['additionalData' => $additionalData]);
    }

    public function getIgnoredAttributes(object $object): array
    {
        if (!$object instanceof AdditionalDataInterface) {
            return [];
        }

        return ['additionalData'];
    }
}
