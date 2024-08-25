<?php

namespace Anodio\Dto\Loaders;

use Anodio\Core\AttributeInterfaces\LoaderInterface;
use Anodio\Core\Attributes\Loader;
use Anodio\Dto\Attributes\Dto;
use DI\ContainerBuilder;
use olvlvl\ComposerAttributeCollector\Attributes;

#[Loader]
class DtoLoader implements LoaderInterface
{

    public function load(ContainerBuilder $containerBuilder): void
    {
        $targets = Attributes::findTargetClasses(Dto::class);
        foreach ($targets as $target) {
            $reflected = new \ReflectionClass($target->name);
            if (!$reflected->getParentClass()) {
                throw new \Exception('The class ' . $target->name . ' must extend Anodio\\Dto\\AbstractDto');
            }
            if ($reflected->getParentClass()->name!='Anodio\\Dto\\AbstractDto') {
                throw new \Exception('The class ' . $target->name . ' must extend Anodio\\Dto\\AbstractDto');
            }
            if (!($target->attribute instanceof Dto)) {
                throw new \Exception('The class ' . get_class($target->attribute) . ' must be an instance of Anodio\\Dto\\Attributes\\Dto');
            }
            $target->attribute->setContainerBuilder($containerBuilder);
            $target->attribute->onClass($target->name);
        }
    }
}