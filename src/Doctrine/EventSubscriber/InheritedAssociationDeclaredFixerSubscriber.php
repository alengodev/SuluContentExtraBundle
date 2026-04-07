<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Doctrine\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

/**
 * Fixes missing 'declared' property on inherited association mappings when
 * Doctrine ORM 3.x is used with report_fields_where_declared: true.
 *
 * Required when extending Sulu's Page or Article entities, where parent-class
 * associations have null 'declared' due to the mapped-superclass inheritance chain.
 */
class InheritedAssociationDeclaredFixerSubscriber
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();

        if (!$metadata->isMappedSuperclass) {
            $this->fixAssociationDeclared($metadata->getName(), $metadata->associationMappings);
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
