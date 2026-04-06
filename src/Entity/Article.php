<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sulu\Article\Domain\Model\Article as SuluArticle;
use Sulu\Content\Domain\Model\DimensionContentInterface;

#[ORM\Entity]
#[ORM\Table(name: 'ar_articles')]
class Article extends SuluArticle
{
    public function createDimensionContent(): DimensionContentInterface
    {
        return new ArticleDimensionContent($this);
    }
}
