<?php

namespace PersistentJMS;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;

use JMS\Serializer\Accessor\DefaultAccessorStrategy;
use JMS\Serializer\Context;
use JMS\Serializer\GenericDeserializationVisitor;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\VisitorInterface;
use JMS\Serializer\XmlDeserializationVisitor;

/**
 * Class ArrayCollectionHandler
 * @package PersistentJMS
 */
class ArrayCollectionHandler implements SubscribingHandlerInterface {

    /**
     * @var DefaultAccessorStrategy
     */
    private $accessor;

    /**
     * @var bool
     */
    private $initializeExcluded = true;

    /**
     * ArrayCollectionHandler constructor.
     * @param bool $initializeExcluded
     */
    public function __construct($initializeExcluded = true) {
        $this->initializeExcluded = $initializeExcluded;

        $this->accessor = new DefaultAccessorStrategy();
    }

    /**
     * @return array
     */
    public static function getSubscribingMethods(): array {
        $methods = [];
        $formats = ['json', 'xml', 'yml'];

        $collectionTypes = [
            'ArrayCollection',
            'Doctrine\Common\Collections\ArrayCollection',
            'PersistentCollection',
            'Doctrine\ORM\PersistentCollection',
            'Doctrine\ODM\MongoDB\PersistentCollection',
            'Doctrine\ODM\PHPCR\PersistentCollection',
        ];

        foreach ($collectionTypes as $type) {
            foreach ($formats as $format) {
                $methods[] = [
                    'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
                    'type' => $type,
                    'format' => $format,
                    'method' => 'serializeCollection',
                ];

                $methods[] = [
                    'direction' => GraphNavigator::DIRECTION_DESERIALIZATION,
                    'type' => $type,
                    'format' => $format,
                    'method' => 'deserializeCollection',
                ];
            }
        }

        return $methods;
    }

    /**
     * @param VisitorInterface $visitor
     * @param Collection $collection
     * @param array $type
     * @param Context $context
     * @return mixed
     */
    public function serializeCollection(
        VisitorInterface $visitor,
        Collection $collection,
        array $type,
        Context $context
    ) {
        $type['name'] = 'array';

        if ($this->initializeExcluded === false) {
            $exclusionStrategy = $context->getExclusionStrategy();

            /** @var ClassMetadata $metadata */
            $metadata = $context->getMetadataFactory()->getMetadataForClass(get_class($collection));

            if ($exclusionStrategy !== null && $exclusionStrategy->shouldSkipClass($metadata, $context)) {
                return $visitor->visitArray([], $type, $context);
            }
        }

        return $visitor->visitArray($collection->toArray(), $type, $context);
    }

    /**
     * @param VisitorInterface $visitor
     * @param $data
     * @param array $type
     * @param Context $context
     * @return Collection
     */
    public function deserializeCollection(
        VisitorInterface $visitor,
        $data,
        array $type,
        Context $context
    ): Collection {
        /** @var GenericDeserializationVisitor|XmlDeserializationVisitor $visitor */

        $field = $context->getCurrentPath()[count($context->getCurrentPath()) - 1];

        /** @var PersistentCollection $collection */
        $persistentCollection = $this->getValue($visitor->getCurrentObject(), $field, $context);

        $arrayCollection = new ArrayCollection($visitor->visitArray($data, $type, $context));

        if ($persistentCollection instanceof PersistentCollection) {
            return $this->preparePersistentCollection($persistentCollection, $arrayCollection, $context);
        } else {
            return $arrayCollection;
        }
    }

    /**
     * @param PersistentCollection $persistentCollection
     * @param ArrayCollection $arrayCollection
     * @param Context $context
     * @return PersistentCollection
     */
    private function preparePersistentCollection(
        PersistentCollection $persistentCollection,
        ArrayCollection $arrayCollection,
        Context $context
    ): PersistentCollection {
        $identifiers = $persistentCollection->getTypeClass()->getIdentifier();

        foreach ($persistentCollection as $index => $item) {
            if ($this->exists($arrayCollection, $item, $context, $identifiers) === -1) {
                $persistentCollection->removeElement($item);
            }
        }

        $itemsToAdd = [];
        foreach ($arrayCollection as $item) {
            $key = $this->exists($persistentCollection, $item, $context, $identifiers);

            if ($key === -1) {
                array_push($itemsToAdd, $item);
            } else {
                $persistentCollection->set($key, $item);
            }
        }

        foreach ($itemsToAdd as $item) {
            $persistentCollection->add($item);
        }

        return $persistentCollection;
    }

    /**
     * @param $object
     * @param string $field
     * @param Context $context
     * @return mixed|null
     */
    private function getValue($object, string $field, Context $context) {
        $propertyMetadata = $context->getMetadataFactory()
            ->getMetadataForClass(get_class($object))->propertyMetadata;

        if (isset($propertyMetadata[$field])) {
            return $this->accessor->getValue($object, $propertyMetadata[$field]);
        }

        return null;
    }

    /**
     * @param Collection $collection
     * @param $search
     * @param Context $context
     * @param array $identifiers
     * @return int
     */
    private function exists(Collection $collection, $search, Context $context, array $identifiers): int {
        $isExist = false;

        foreach ($collection as $key => $item) {
            foreach ($identifiers as $identifier) {
                if ($this->getValue($item, $identifier, $context)
                    !== $this->getValue($search, $identifier, $context)) {
                    $isExist = false;

                    break;
                } else {
                    $isExist = true;
                }
            }

            if ($isExist) {
                return $key;
            }
        }

        return -1;
    }

}
