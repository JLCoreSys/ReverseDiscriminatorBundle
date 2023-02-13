<?php
/**
 * CoreSystems (c) 2023
 * Author: Josh McCreight<jmccreight@shaw.ca>
 */

declare(strict_types=1);

namespace CoreSys\ReverseDiscriminator\Annotations;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class ChildDiscriminator
 * @package CoreSys\ReverseDiscriminator\Annotations
 */
#[Annotation()]
#[Target('CLASS')]
class DiscriminatorEntry
{
    public string $value;
}
