<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\Page as SuluPage;

#[ORM\Entity]
#[ORM\Table(name: 'pa_pages')]
class Page extends SuluPage
{
    public function createDimensionContent(): DimensionContentInterface
    {
        return new PageDimensionContent($this);
    }
}
