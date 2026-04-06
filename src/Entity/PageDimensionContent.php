<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Entity;

use Alengo\SuluContentExtraBundle\Model\AdditionalDataInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sulu\Page\Domain\Model\PageDimensionContent as SuluPageDimensionContent;

#[ORM\Entity]
#[ORM\Table(name: 'pa_page_dimension_contents')]
class PageDimensionContent extends SuluPageDimensionContent implements AdditionalDataInterface
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
        return $this->additionalData ?? [];
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
