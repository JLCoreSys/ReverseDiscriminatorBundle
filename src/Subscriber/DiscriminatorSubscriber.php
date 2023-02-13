<?php
/**
 * CoreSystems (c) 2023
 * Author: Josh McCreight<jmccreight@shaw.ca>
 */

declare(strict_types=1);

namespace CoreSys\ReverseDiscriminator\Subscriber;

use CoreSys\ReverseDiscriminator\Annotations\DiscriminatorEntry;
use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Exception;
use ReflectionClass;
use ReflectionException;

/**
 * Class DiscriminatorSubscriber
 * @package CoreSys\ReverseDiscriminator\Subscriber
 */
class DiscriminatorSubscriber implements EventSubscriber
{
    protected array $map;
    protected array $cachedMap;
    protected AnnotationReader $reader;

    /**
     * DiscriminatorSubscriber constructor.
     *
     * @throws AnnotationException
     */
    public function __construct()
    {
        $this->reader = new AnnotationReader();
        $this->map = [];
        $this->cachedMap = [];
    }

    /**
     * An array of subscribed events
     *
     * @return array|string[]
     */
    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata
        ];
    }

    /**
     * Load the class Metadata
     *
     * @param LoadClassMetadataEventArgs $event
     * @throws Exception
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $event)
    {
        $this->map = [];
        $class = $event->getClassMetadata()->name;

        if (array_key_exists($class, $this->cachedMap)) {
            $this->overrideMetadata($event, $class);

            return;
        }

        if ($this->extractEntry($class)) {
            $this->checkClass($class);
        } else {
            return;
        }

        $dMap = array_flip($this->map);

        foreach ($this->map as $className => &$discriminator) {
            $this->addToCachedMap($className, 'map', $dMap)
                ->addToCachedMap($className, 'discr', $discriminator);
        }

        $this->overrideMetadata($event, $class);
    }

    /**
     * Override Metadata for a given class
     *
     * @param LoadClassMetadataEventArgs $event
     * @param string                     $class
     */
    protected function overrideMetadata(LoadClassMetadataEventArgs $event, string $class)
    {
        $event->getClassMetadata()->discriminatorMap = $this->cachedMap[ $class ][ 'map' ];
        $event->getClassMetadata()->discriminatorValue = $this->cachedMap[ $class ][ 'discr' ];

        if (isset($this->cachedMap[ $class ][ 'isParent' ]) && $this->cachedMap[ $class ][ 'isParent' ]) {
            $subClasses = $this->cachedMap[ $class ][ 'map' ];
            unset($subClasses[ $this->cachedMap[ $class ][ 'discr' ] ]);
            $event->getClassMetadata()->subClasses = array_values($subClasses);
        }
    }

    /**
     * Extract the DiscriminatorEntry from the class
     *
     * @param string $class
     * @return bool
     * @throws ReflectionException
     */
    protected function extractEntry(string $class)
    {
        $rc = new ReflectionClass($class);
        $annotation = $this->reader->getClassAnnotation($rc, DiscriminatorEntry::class);
        if (!empty($annotation)) {
            if (in_array($value = $annotation->value, $this->map)) {
                throw new Exception(sprintf('Duplicate discriminator map entry `%s` in `%s`', $value, $class));
            }

            $this->map[ $class ] = $value;

            return true;
        }

        return false;
    }

    /**
     * Check the class for mappings/annotations
     *
     * @param string $class
     * @throws ReflectionException
     */
    protected function checkClass(string $class)
    {
        $rc = new ReflectionClass($class);
        $parent = $rc->getParentClass();

        if (!empty($parent)) {
            $parent = $parent->name;
            $this->checkClass($parent);
        } else {
            $this->addToCachedMap($class, 'isParent', true);
            $this->checkClassChildren($class);
        }
    }

    /**
     * Add an entry to the cached map
     *
     * @param string $class
     * @param string $key
     * @param null   $value
     * @return DiscriminatorSubscriber
     */
    protected function addToCachedMap(string $class, string $key, $value = null): DiscriminatorSubscriber
    {
        if (!is_array($this->cachedMap)) {
            $this->cachedMap = [];
        }

        if (!isset($this->cachedMap[ $class ]) || !is_array($this->cachedMap[ $class ])) {
            $this->cachedMap[ $class ] = [ 'isParent' => false ];
        }

        $this->cachedMap[ $class ][ $key ] = $value;

        return $this;
    }

    /**
     * Check class children for entries
     *
     * @param string $class
     * @throws ReflectionException
     */
    public function checkClassChildren(string $class)
    {
        foreach ($this->getSubClasses($class) as $className) {
            $rc = new ReflectionClass($className);
            $parent = $rc->getParentClass();
            if (!empty($parent)) {
                $parent = $parent->name;
                if ($parent === $class) {
                    if ($hasDiscriminatorEntry = $this->extractEntry($className)) {
                        if (!array_key_exists($className, $this->map)) {
                            $this->checkClassChildren($className);
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the sub-classes for a given class
     *
     * @param string $class
     * @return array
     * @throws ReflectionException
     */
    protected function getSubClasses(string $class)
    {
        $subClasses = [];

        foreach (get_declared_classes() as $potentialSubClass) {
            $rc = new ReflectionClass($potentialSubClass);
            if ($rc->isSubclassOf($class)) {
                $subClasses[] = $potentialSubClass;
            }
        }

        return $subClasses;
    }
}
