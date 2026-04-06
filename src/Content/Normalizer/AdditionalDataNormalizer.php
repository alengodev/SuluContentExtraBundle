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

        return \array_merge($normalizedData, $object->getAdditionalData());
    }

    public function getIgnoredAttributes(object $object): array
    {
        if (!$object instanceof AdditionalDataInterface) {
            return [];
        }

        return ['additionalData'];
    }
}
