<?php
/**
 * CoreSystems (c) 2020
 * Author: Josh McCreight<jmccreight@shaw.ca>
 */

declare( strict_types = 1 );

namespace CoreSys\ReverseDiscriminator\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * Class ChildDiscriminator
 * @package CoreSys\ReverseDiscriminator\Annotations
 * @Annotation
 * @Annotation\Target("CLASS")
 */
class DiscriminatorEntry
{
    /**
     * @var string
     */
    public $value;
}