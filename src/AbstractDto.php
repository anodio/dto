<?php

namespace Anodio\Dto;

use Anodio\Core\ContainerStorage;
use DI\Attribute\Inject;
use DI\Container;

abstract class AbstractDto implements \JsonSerializable
{
    protected $onlied = [];

    protected $excepted = [];

    public static function from(?array $data = [], bool $strict = false): static {
        if (is_null($data)) {
            $data = [];
        }
        return static::fromArray($data, $strict);
    }

    public static function fromArray(?array $data = [], bool $strict = false): static
    {
        if (is_null($data)) {
            $data = [];
        }
        $dto = new static();
        $container = ContainerStorage::getContainer();

        if (!$container->has('instructions_dto_'.get_class($dto))) {
            //lets try fill how we can
            throw new \Exception('The class ' . get_class($dto) . ' must have instructions');
        }

        $instructions = $container->get('instructions_dto_'.get_class($dto));

        foreach ($instructions as $property => $instruction) {
            if (!array_key_exists($property, $data)) {
                if ($strict) {
                    throw new \Exception('The property ' . $property . ' is required in the class ' . get_class($dto));
                } else {
                    continue;
                }
            }
            if ($instruction['type']==='skip') {
                continue;
            }
            if ($instruction['type']==='dto') {
                if (is_object($data[$property]) && $data[$property] instanceof AbstractDto) {
                    $dto->{$property} = $data[$property];
                } elseif (is_array($data[$property])) {
                    $dto->{$property} = $instruction['dto']::fromArray($data[$property], $strict);
                } elseif (is_null($data[$property]) && isset($instruction['nullable']) && $instruction['nullable']) {
                    $dto->{$property} = null;
                } else {
                    throw new \Exception('The property ' . $property . ' must be an array or an instance of ' . $instruction['dto']);
                }

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

    public function only(...$properties): static
    {
        $this->onlied = array_merge($this->onlied, $properties);
        return $this;
    }

    public function except(...$properties): static
    {
        $this->excepted = array_merge($this->excepted, $properties);
        return $this;
    }

    public function toArray($onlied = [], $excepted=[]): array
    {
        $onliedCommon = array_merge($this->onlied, $onlied);
        $exceptedCommon = array_merge($this->excepted, $excepted);
        $array = [];
        $container = ContainerStorage::getContainer();
        if (!$container->has('instructions_dto_'.get_called_class())) {
            foreach ($this as $property => $value) {
                if (is_object($value) && method_exists($value, 'toArray')) {
                    $array[$property] = $value->toArray();
                } else {
                    $array[$property] = $value;
                }
            }
        } else {
            $instructions = $container->get('instructions_dto_'.get_called_class());
            foreach ($instructions as $property => $instruction) {
                if (!$this->shouldWeRenderFieldBecauseOfExceptAndOnly($property, $onliedCommon, $exceptedCommon)) {
                    continue;
                }
                if ($instruction['type']==='dto') {
                    if (isset($this->{$property}) && !is_null($this->{$property})) {
                        $onliedForProperty = [];
                        foreach ($onliedCommon as $onlied) {
                            if (str_starts_with($onlied, $property.'.')) {
                                $onliedForProperty[] = str_replace($property.'.', '', $onlied);
                            }
                        }
                        $exceptedForProperty = [];
                        foreach ($exceptedCommon as $excepted) {
                            if (str_starts_with($excepted, $property.'.')) {
                                $exceptedForProperty[] = str_replace($property.'.', '', $excepted);
                            }
                        }
                        $array[$property] = $this->{$property}->toArray($onliedForProperty, $exceptedForProperty);
                    }
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
        }

        return $array;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * This function should return false if we should skip field, and true, if we need to render it.
     * If field is marked as excepted, we should skip it in any case.
     * If field is marked as onlied, we should render it in any case.
     * If field is defined as excepted and onlied, we need to skip it.
     * If some fields defined as excepted, and no onlied fields defined - we need to render all fields except excepted.
     * If some fields defined as onlied, and no excepted fields defined - we need to render only onlied fields.
     * If some fields defined as onlied, and some fields defined as excepted - we need to render only onlied fields, except excepted.
     * If no fields defined as onlied and excepted - we need to render all fields.
     * @param int|string $property
     * @param array $onliedCommon
     * @param array $exceptedCommon
     * @return void
     */
    private function shouldWeRenderFieldBecauseOfExceptAndOnly(string $property, array $onliedCommon, array $exceptedCommon): bool
    {

        if (count($exceptedCommon)==0 && count($onliedCommon)==0) {
            return true;
        }

        if (count($exceptedCommon)>0 && count($onliedCommon)==0) {
            foreach ($exceptedCommon as $exceptedField) {
                if ($property===$exceptedField) {
                    return false;
                }
            }
            return true;
        }

        if (count($onliedCommon)>0 && count($exceptedCommon)==0) {
            $allowed = false;
            foreach ($onliedCommon as $onliedField) {
                if ($property===$onliedField || str_starts_with($onliedField, $property.'.')) {
                    $allowed = true;
                    break;
                }
            }
            if ($allowed) {
                return true;
            } else {
                return false;
            }
        }

        if (count($exceptedCommon)>0 && count($onliedCommon)>0) {
            foreach ($exceptedCommon as $exceptedField) {
                if ($exceptedField===$property) {
                    return false;
                }
            }
            $allowed = false;
            foreach ($onliedCommon as $onliedField) {
                if ($onliedField===$property || str_starts_with($onliedField, $property.'.')) {
                    $allowed = true;
                    break;
                }
            }
            if ($allowed) {
                return true;
            } else {
                return false;
            }
        }
        return false;

    }

}
