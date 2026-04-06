<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\Merger;

use Alengo\SuluContentExtraBundle\Model\AdditionalDataInterface;
use Sulu\Content\Application\ContentMerger\Merger\MergerInterface;

final class AdditionalDataMerger implements MergerInterface
{
    public function merge(object $targetObject, object $sourceObject): void
    {
        if (!$targetObject instanceof AdditionalDataInterface) {
            return;
        }

        if (!$sourceObject instanceof AdditionalDataInterface) {
            return;
        }

        $targetObject->setAdditionalData(\array_merge(
            $targetObject->getAdditionalData(),
            $sourceObject->getAdditionalData(),
        ));
    }
}
