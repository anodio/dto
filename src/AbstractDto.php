<?php

namespace Anodio\Dto;

use Anodio\Core\ContainerStorage;
use DI\Attribute\Inject;
use DI\Container;

abstract class AbstractDto implements \JsonSerializable
{
    protected $onlied = [];

    protected $excepted = [];

    protected function __construct() {

    }

    public static function fromArray(array $data = [], bool $strict = false): static
    {
        $dto = new static();
        $container = ContainerStorage::getContainer();
        $instructions = $container->get('instructions_dto_'.get_class($dto));

        if ($instructions===null) {
            throw new \Exception('The class ' . get_class($dto) . ' was not registered as dto. There is no instructions to build it from array.');
        }

        foreach ($instructions as $property => $instruction) {
            if (!array_key_exists($property, $data)) {
                if ($strict) {
                    throw new \Exception('The property ' . $property . ' is required in the class ' . get_class($dto));
                } else {
                    continue;
                }
            }
            if ($instruction['type']==='dto') {
                $dto->{$property} = $instruction['dto']::fromArray($data[$property], $strict);
            } elseif ($instruction['type']==='arrayOf') {
                $dto->{$property} = [];
                foreach ($data[$property] as $item) {
                    $dto->{$property}[] = $instruction['dto']::fromArray($item, $strict);
                }
            } elseif ($instruction['type']==='array') {
                $dto->{$property} = $data[$property];
            } else {
                $dto->{$property} = $data[$property];
            }
        }
        return $dto;
    }

    public function only(...$properties): void
    {
        $this->onlied = array_merge($this->onlied, $properties);
    }

    public function except(...$properties): void
    {
        $this->excepted = array_merge($this->excepted, $properties);
    }

    public function toArray(): array
    {
        $array = [];
        $container = ContainerStorage::getContainer();
        $instructions = $container->get('instructions_dto_'.get_called_class());
        if ($instructions===null) {
            throw new \Exception('The class ' . get_called_class() . ' was not registered as dto. There is no instructions to build it to array.');
        }
        foreach ($instructions as $property => $instruction) {
            if (count($this->excepted)>0) {
                if (in_array($property, $this->excepted)) {
                    continue;
                }
            } else {
                if (count($this->onlied)>0) {
                    if (!in_array($property, $this->onlied)) {
                        continue;
                    }
                }
            }
            if ($instruction['type']==='dto') {
                $array[$property] = $this->{$property}->toArray();
            } elseif ($instruction['type']==='arrayOf') {
                $array[$property] = [];
                foreach ($this->{$property} as $item) {
                    $array[$property][] = $item->toArray();
                }
            } elseif ($instruction['type']==='array') {
                $array[$property] = $this->{$property};
            } else {
                $array[$property] = $this->{$property};
            }
        }
        return $array;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

}