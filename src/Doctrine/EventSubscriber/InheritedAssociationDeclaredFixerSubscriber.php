<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Doctrine\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

/**
 * Fixes association mappings when Doctrine ORM 3.x is used with
 * report_fields_where_declared: true and Sulu entity overrides are in place.
 *
 * 1. Fixes missing 'declared' property on inherited association mappings.
 *    Required when extending Sulu's Page or Article entities, where parent-class
 *    associations have null 'declared' due to the mapped-superclass inheritance chain.
 *
 * 2. Fixes 'targetEntity' references that still point to Sulu's original (overridden)
 *    entity classes. Without this, Doctrine tries to generate proxies for the original
 *    Sulu classes even though they are replaced, causing missing-proxy errors when
 *    auto_generate_proxy_classes is set to false/0 in production.
 */
class InheritedAssociationDeclaredFixerSubscriber
{
    /**
     * @param array<string, string> $targetEntityOverrides Map of original class => replacement class
     */
    public function __construct(
        private readonly array $targetEntityOverrides = [],
    ) {}

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();

        if (!empty($this->targetEntityOverrides)) {
            $this->fixTargetEntities($metadata->associationMappings);
        }

        if (!$metadata->isMappedSuperclass) {
            $this->fixAssociationDeclared($metadata->getName(), $metadata->associationMappings);
        }
    }

    /**
     * Replaces targetEntity references to overridden Sulu classes with the configured
     * concrete classes, so Doctrine never generates proxies for the original classes.
     *
     * @param iterable<string, \Doctrine\ORM\Mapping\AssociationMapping> $associationMappings
     */
    private function fixTargetEntities(iterable $associationMappings): void
    {
        foreach ($associationMappings as $mapping) {
            if (isset($this->targetEntityOverrides[$mapping->targetEntity])) {
                $mapping->targetEntity = $this->targetEntityOverrides[$mapping->targetEntity];
            }
        }
    }

    private function fixAssociationDeclared(string $className, iterable $associationMappings): void
    {
        $rc = new \ReflectionClass($className);

        /** @var array<string, \ReflectionClass<object>> $parentReflections */
        $parentReflections = [];
        foreach (\class_parents($className) as $parentClass) {
            $parentReflections[$parentClass] = new \ReflectionClass($parentClass);
        }

        foreach ($associationMappings as $fieldName => $mapping) {
            if (null !== $mapping->declared) {
                continue;
            }

            if ($rc->hasProperty($fieldName)) {
                $mapping->declared = $rc->getProperty($fieldName)->getDeclaringClass()->getName();
                continue;
            }

            foreach ($parentReflections as $parentClass => $parentRc) {
                foreach ($parentRc->getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
                    if ($rp->getName() === $fieldName) {
                        $mapping->declared = $parentClass;
                        continue 3;
                    }
                }
            }
        }
    }
}
