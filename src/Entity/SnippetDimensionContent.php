<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Entity;

use Alengo\SuluContentExtraBundle\Model\AdditionalDataInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sulu\Snippet\Domain\Model\SnippetDimensionContent as SuluSnippetDimensionContent;

#[ORM\Entity]
#[ORM\Table(name: 'sn_snippet_dimension_contents')]
class SnippetDimensionContent extends SuluSnippetDimensionContent implements AdditionalDataInterface
{
    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'additionalData', type: Types::JSON, options: ['default' => '{}'])]
    private array $additionalData = [];

    /**
     * @return array<string, mixed>
     */
    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }

    /**
     * @param array<string, mixed> $additionalData
     */
    public function setAdditionalData(array $additionalData): static
    {
        $this->additionalData = $additionalData;

        return $this;
    }
}
