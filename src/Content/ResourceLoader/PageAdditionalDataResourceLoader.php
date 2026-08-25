<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\ResourceLoader;

final class PageAdditionalDataResourceLoader extends AbstractAdditionalDataResourceLoader
{
    public static function getKey(): string
    {
        return 'page';
    }
}
