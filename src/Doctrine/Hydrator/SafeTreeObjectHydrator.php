<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Doctrine\Hydrator;

use Gedmo\Exception\InvalidMappingException;
use Gedmo\Tree\Hydrator\ORM\TreeObjectHydrator;

/**
 * Fixes Gedmo's TreeObjectHydrator::getChildrenField() which unconditionally accesses
 * $associationMapping['mappedBy'] on all associations, including owning-side ManyToOne
 * mappings that do not have a 'mappedBy' property in Doctrine ORM 3.x.
 */
class SafeTreeObjectHydrator extends TreeObjectHydrator
{
    protected function getChildrenField($entityClass)
    {
        $meta = $this->getClassMetadata($entityClass);

        foreach ($meta->getReflectionProperties() as $property) {
            if (!$meta->hasAssociation($property->getName())) {
                continue;
            }

            // Skip owning-side associations — only inverse sides have 'mappedBy'
            if (!$meta->isAssociationInverseSide($property->getName())) {
                continue;
            }

            $associationMapping = $meta->getAssociationMapping($property->getName());

            if ($associationMapping['mappedBy'] !== $this->getParentField()) {
                continue;
            }

            return $associationMapping['fieldName'];
        }

        throw new InvalidMappingException('The children property could not be found. It is identified through the `mappedBy` annotation to your parent property.');
    }
}
