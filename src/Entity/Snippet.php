<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Snippet\Domain\Model\Snippet as SuluSnippet;

#[ORM\Entity]
#[ORM\Table(name: 'sn_snippets')]
class Snippet extends SuluSnippet
{
    public function createDimensionContent(): DimensionContentInterface
    {
        return new SnippetDimensionContent($this);
    }
}
