<?php

namespace Anodio\Dto\Attributes;

use Anodio\Core\Abstraction\AbstractAttribute;
use Anodio\Dto\AbstractDto;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Dto extends AbstractAttribute
{
    public function onClass(string $className): bool
    {
        $reflectionClass = new \ReflectionClass($className);
        if ($reflectionClass->isAbstract()) {
            throw new \Exception('The class ' . $className . ' must not be abstract');
        }
        if (!$reflectionClass->getParentClass()) {
            throw new \Exception('The class ' . $className . ' must extend the class Anodio\\Dto\\AbstractDto');
        }
        if (!$reflectionClass->getParentClass()->getName() === 'Anodio\\Dto\\AbstractDto') {
            throw new \Exception('The class ' . $className . ' must extend the class Anodio\\Dto\\AbstractDto');
        }
        $instructions = [];
        foreach ($reflectionClass->getProperties() as $property) {
            if ($property->hasType()) {
                if ($property->getType()->getName()==AbstractDto::class) {
                    $instructions[$property->getName()] = [
                        'type' => 'dto',
                        'dto' => $property->getType()->getName()
                    ];
                }
            } elseif ($property->getType()==='array') {
                $instructions[$property->getName()] = [
                    'type' => 'array',
                    'dto' => null
                ];
            } elseif (str_starts_with($property->getType(), 'array')) {
                //it can be array of DTO. We need to parse it
                $exploded = explode('|', $property->getType());
                if (count($exploded)>2) {
                    throw new \Exception('The property ' . $property->getName() . ' of the class ' . $className . ' has unsupported type. It should be array|DTO or DTO|array only.');
                }
                if (trim($exploded[0])==='array') {
                    $type = trim($exploded[1]);
                    $typeReflection = new \ReflectionClass($type);
                    if (!$typeReflection->getParentClass()) {
                        throw new \Exception('The type ' . $type . ' in '.$reflectionClass->getName().' must extend the class Anodio\\Dto\\AbstractDto');
                    }
                    if ($typeReflection->getParentClass()->getName()!==AbstractDto::class) {
                        throw new \Exception('The type ' . $type . ' in '.$reflectionClass->getName().' must extend the class Anodio\\Dto\\AbstractDto');
                    }
                    $realType = $typeReflection->getParentClass()->getName();

                    $instructions[$property->getName()] = [
                        'type' => 'arrayOf',
                        'dto' => $realType
                    ];
                } else {
                    $type = trim($exploded[0]);
                    $typeReflection = new \ReflectionClass($type);
                    if (!$typeReflection->getParentClass()) {
                        throw new \Exception('The type ' . $type . ' in '.$reflectionClass->getName().' must extend the class Anodio\\Dto\\AbstractDto');
                    }
                    if ($typeReflection->getParentClass()->getName()!==AbstractDto::class) {
                        throw new \Exception('The type ' . $type . ' in '.$reflectionClass->getName().' must extend the class Anodio\\Dto\\AbstractDto');
                    }
                    $realType = $typeReflection->getParentClass()->getName();

                    $instructions[$property->getName()] = [
                        'type' => 'arrayOf',
                        'dto' => $realType
                    ];
                }
            } else {
                $instructions[$property->getName()] = [
                    'type' => 'common',
                    'dto' => null
                ];
            }
            $className::$instructions = $instructions;
        }


        return true;
    }
}