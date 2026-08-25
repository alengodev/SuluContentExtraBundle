<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\ResourceLoader;

final class ArticleAdditionalDataResourceLoader extends AbstractAdditionalDataResourceLoader
{
    public static function getKey(): string
    {
        return 'article';
    }
}
