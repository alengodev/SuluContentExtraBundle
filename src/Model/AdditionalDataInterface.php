<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Model;

interface AdditionalDataInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getAdditionalData(): array;

    /**
     * @param array<string, mixed> $additionalData
     */
    public function setAdditionalData(array $additionalData): static;
}
