<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\ResourceLoader;

final class SnippetAdditionalDataResourceLoader extends AbstractAdditionalDataResourceLoader
{
    public static function getKey(): string
    {
        return 'snippet';
    }
}
