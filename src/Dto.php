<?php

/*
 * This file is part of the Laradev project
 * (c) Darras Florian florian@laradev.ca
 */

namespace Laradev\Dto;

abstract class Dto implements DtoInterface
{
    protected array $initialized_properties = [];

    public static function create(array|object $data, ...$arg): static
    {
        if (is_array($data)) {
            return (new static(...$arg))->fromArray($data);
        } else {
            return (new static(...$arg))->fromObject($data);
        }
    }

    public function clone(...$args): static
    {
        return (new static(...$args))->fromObject($this);
    }

    public function fromArray(array $data): static
    {
        $ref = new \ReflectionClass($this);

        foreach ($ref->getProperties() as $property) {
            if (!isset($data[$property->getName()])) {
                continue;
            }

            $value = null;

            if ($property->getType() instanceof \ReflectionUnionType){
                foreach ($property->getType()->getTypes() as $type) {
                    try {
                        $value = $this->extractValueFromType($type, $data[$property->getName()]);
                    } catch (\LogicException $e) {
                        continue;
                    }
                }
            } else {
                $value = $this->extractValueFromType($property->getType(), $data[$property->getName()]);
            }

            $formattedProperty = ucfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $property->getName()))));
            $setter = "set{$formattedProperty}";
            $adder = "add{$formattedProperty}";

            if ($ref->hasMethod($adder) && is_array($value)) {
                foreach ($value as $item) {
                    $this->$adder($item);
                }
            } elseif ($ref->hasMethod($setter)) {
                $this->$setter($value);
            } elseif ($property->isPublic()) {
                $this->{$property->getName()} = $value;
            }

            $this->initialized_properties[] = $property->getName();
        }

        return $this;
    }

    public function fromObject(object $object): static
    {
        $from = new \ReflectionClass($object);
        $target = new \ReflectionClass($this);

        foreach ($from->getProperties() as $fromProperty) {
            if (!$fromProperty->isInitialized($object)) {
                continue;
            }

            foreach ($target->getProperties() as $targetProperty) {
                if ($targetProperty->getName() !== $fromProperty->getName()) {
                    continue;
                }

                $value = null;

                if ($from->hasMethod($getters = 'get'.ucfirst($fromProperty->getName()))) {
                    $value = $object->{$getters}();
                } else if ($fromProperty->isPublic()) {
                    $value = $fromProperty->getValue($object);
                }

                if ($targetProperty->getType() instanceof \ReflectionUnionType){
                    foreach ($targetProperty->getType()->getTypes() as $type) {
                        try {
                            $value = $this->extractValueFromType($type, $value);
                        } catch (\LogicException $e) {
                            continue;
                        }
                    }
                } else {
                    $value = $this->extractValueFromType($targetProperty->getType(), $value);
                }

                if ($target->hasMethod($setters = 'set'.ucfirst($targetProperty->getName()))) {
                    $this->{$setters}($value);
                } elseif ($targetProperty->isPublic()) {
                    $this->{$targetProperty->getName()} = $value;
                }

                $this->initialized_properties[] = $fromProperty->getName();
            }
        }

        return $this;
    }

    private function isObjectAlreadyInstantiate(\ReflectionClass $ref, string $class): bool
    {
        if ($ref->getName() === $class) {
            return true;
        }

        if (in_array($class, array_keys($ref->getInterfaces()))) {
            return true;
        }

        if ($ref->getParentClass() instanceof \ReflectionClass) {
            return $this->isObjectAlreadyInstantiate($ref->getParentClass(), $class);
        }

        return false;
    }

    private function extractValueFromType(\ReflectionType $type, mixed $data): mixed
    {
        if ($type->isBuiltin()) {
            return $data;
        }

        $class = (string) $type;

        if (is_object($data) && $this->isObjectAlreadyInstantiate(new \ReflectionClass($data), $class)) {
            return $data;
        }

        $targetRef = new \ReflectionClass($class);

        if ($targetRef->isEnum()) {
            return $class::tryFrom($data);
        }

        if (!is_array($data) && !is_object($data)) {
            return $data;
        }

        if (in_array(DtoInterface::class, array_keys($targetRef->getInterfaces()))) {
            return $class::create($data);
        }

        $constructor = $targetRef->getConstructor();

        if (null !== $constructor && count($constructor->getParameters()) > 0) {
            $params = [];

            if (is_array($data)) {
                foreach ($constructor->getParameters() as $key => $param) {
                    if (isset($data[$param->getName()])) {
                        $params[$key] = $data[$param->getName()];
                    }
                }
            } else {
                $fromRef = new \ReflectionClass($data);
                foreach ($constructor->getParameters() as $key => $param) {
                    try {
                        // exception if not found
                        $fromProperty = $fromRef->getProperty($param->getName());

                        if ($fromProperty->isPublic()) {
                            $params[$key] = $data->{$param->getName()};
                        } elseif ($fromRef->hasMethod($getter = 'get'.ucfirst($param->getName()))) {
                            $params[$key] = $data->{$getter}();
                        } elseif ($fromRef->hasMethod($getter = $param->getName())) {
                            $params[$key] = $data->{$getter}();
                        }
                    } catch (\ReflectionException $exception) {
                        continue;
                    }
                }
            }

            return (new $class(...$params));
        }

        throw new \LogicException("Cannot instantiate $class");
    }

    public function getInitializedProperties(): array
    {
        return $this->initialized_properties;
    }

    abstract public function toArray(): array;

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}