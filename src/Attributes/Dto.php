<?php

namespace Anodio\Dto\Attributes;

use Anodio\Core\Abstraction\AbstractAttribute;
use Anodio\Dto\AbstractDto;
use DI\ContainerBuilder;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Dto extends AbstractAttribute
{

    private ContainerBuilder $containerBuilder;

    public function setContainerBuilder(ContainerBuilder $containerBuilder): void
    {
        $this->containerBuilder = $containerBuilder;
    }

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
            if (!$property->isPublic()) {
                continue;
            }
            if (!$property->hasType()) {
                throw new \Exception('The property ' . $property->getName() . ' of the class ' . $className . ' must have a type');
            }
            if (($property->getType() instanceof \ReflectionType && !($property->getType() instanceof \ReflectionUnionType)) && in_array($property->getType()->getName(), ['string', 'int', 'bool', 'float'])) {
                $instructions[$property->getName()] = [
                    'type' => 'common',
                    'dto' => null
                ];
            } elseif (($property->getType() instanceof \ReflectionType && !($property->getType() instanceof \ReflectionUnionType)) && $property->getType()->getName()==='array') {
                $instructions[$property->getName()] = [
                    'type' => 'array',
                    'dto' => null
                ];
            } elseif ($property->getType() instanceof \ReflectionUnionType) {
                if (!count($property->getType()->getTypes())==2) {
                    throw new \Exception('The property ' . $property->getName() . ' of the class ' . $className . ' has unsupported type. It should be array|DTO or DTO|array only.');
                }
                $arrayKeyWordKeyFound = null;
                foreach ($property->getType()->getTypes() as $arrayKeywordKey => $type) {
                    if ($type->getName()==='array') {
                        $arrayKeyWordKeyFound = $arrayKeywordKey;
                    }
                }

                //it can be array of DTO. We need to parse it
                if ($arrayKeyWordKeyFound===0) {
                    $type = trim($property->getType()->getTypes()[1]->getName());
                    $typeReflection = new \ReflectionClass($type);
                    if (!$typeReflection->getParentClass()) {
                        throw new \Exception('The type ' . $type . ' in '.$reflectionClass->getName().' must extend the class Anodio\\Dto\\AbstractDto');
                    }
                    if ($typeReflection->getParentClass()->getName()!==AbstractDto::class) {
                        throw new \Exception('The type ' . $type . ' in '.$reflectionClass->getName().' must extend the class Anodio\\Dto\\AbstractDto');
                    }
                    $realType = $typeReflection->getName();

                    $instructions[$property->getName()] = [
                        'type' => 'arrayOf',
                        'dto' => $realType
                    ];
                } else {
                    $type = trim($property->getType()->getTypes()[0]->getName());
                    $typeReflection = new \ReflectionClass($type);
                    if (!$typeReflection->getParentClass()) {
                        throw new \Exception('The type ' . $type . ' in '.$reflectionClass->getName().' must extend the class Anodio\\Dto\\AbstractDto');
                    }
                    if ($typeReflection->getParentClass()->getName()!==AbstractDto::class) {
                        throw new \Exception('The type ' . $type . ' in '.$reflectionClass->getName().' must extend the class Anodio\\Dto\\AbstractDto');
                    }
                    $realType = $typeReflection->getName();

                    $instructions[$property->getName()] = [
                        'type' => 'arrayOf',
                        'dto' => $realType
                    ];
                }
            } else {
                if (!$property->getType()->isBuiltin()) {
                    try {
                        $typeReflection = new \ReflectionClass($property->getType()->getName());
                        if (!$typeReflection->getParentClass()) {
                            $instructions[$property->getName()] = [
                                'type' => 'skip'
                            ];
                            continue;
                        }
                        if ($typeReflection->getParentClass()->getName()!==AbstractDto::class) {
                            $instructions[$property->getName()] = [
                                'type' => 'skip'
                            ];
                            continue;
                        }
                        $instructions[$property->getName()] = [
                            'type' => 'dto',
                            'dto' => $property->getType()->getName(),
                            'nullable'=>$property->getType()->allowsNull()
                        ];
                    } catch (\ReflectionException $e) {
                        $instructions[$property->getName()] = [
                            'type' => 'common',
                            'dto' => null
                        ];
                    }
                }
            }
        }
        $this->containerBuilder->addDefinitions([
            'instructions_dto_'.$className => \Di\factory(function(array $instructions) {
                return $instructions;
            })->parameter('instructions', $instructions)
        ]);

        return true;
    }
}
