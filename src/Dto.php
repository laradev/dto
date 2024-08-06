<?php

/*
 * This file is part of the Laradev project
 * (c) Darras Florian florian@laradev.ca
 */

namespace Laradev\Dto;

abstract class Dto implements DtoInterface
{
    protected array $initialized_properties = [];

    public static function create(array|object $data, ...$arg): self
    {
        if (is_array($data)) {
            return (new static(...$arg))->fromArray($data);
        } else {
            return (new static(...$arg))->fromObject($data);
        }
    }

    public function clone(...$args): self
    {
        return (new static(...$args))->fromObject($this);
    }

    /**
     * @deprecated Please use fromArray() method
     * @param array $data
     * @return $this
     */
    public function setData(array $data): self
    {
        return $this->fromArray($data);
    }

    public function fromArray(array $data): self
    {
        $ref = new \ReflectionClass($this);

        foreach ($ref->getProperties() as $property) {
            if (!isset($data[$property->getName()])) {
                continue;
            }

            $value = $this->extractValueFromType($property->getType(), $data[$property->getName()]);
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

    public function fromObject(object $object): self
    {
        $reflectionObject = new \ReflectionClass($object);
        $reflectionClass = new \ReflectionClass($this);

        foreach ($reflectionObject->getProperties() as $reflectionObjectProperty) {
            foreach ($reflectionClass->getProperties() as $reflectionClassProperty) {
                if (!$reflectionObjectProperty->isInitialized($object)) {
                    continue;
                }

                if ($reflectionClassProperty->getName() === $reflectionObjectProperty->getName()) {
                    $value = null;

                    if ($reflectionObject->hasMethod($getters = 'get'.ucfirst($reflectionObjectProperty->getName()))) {
                        $value = $object->{$getters}();
                    } else if ($reflectionObjectProperty->isPublic()) {
                        $value = $reflectionObjectProperty->getValue($object);
                    }

                    $value = $this->extractValueFromType($reflectionClassProperty->getType(), $value);

                    if ($reflectionClass->hasMethod($setters = 'set'.ucfirst($reflectionClassProperty->getName()))) {
                        $this->{$setters}($value);
                    } elseif ($reflectionClassProperty->isPublic()) {
                        $this->{$reflectionClassProperty->getName()} = $value;
                    }

                    $this->initialized_properties[] = $reflectionObjectProperty->getName();
                }
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

        $ref = new \ReflectionClass($class);

        if ($ref->isEnum()) {
            return $class::tryFrom($data);
        }

        if (!is_array($data)) {
            return $data;
        }

        if (in_array(DtoInterface::class, array_keys($ref->getInterfaces()))) {
            if (is_array($data)) {
                return (new $class())->fromArray($data);
            } else {
                return (new $class())->fromObject($data);
            }
        }

        $constructor = $ref->getConstructor();

        if (null !== $constructor && count($constructor->getParameters()) > 0) {
            $params = [];

            foreach ($constructor->getParameters() as $key => $param) {
                try {
                    if (is_array($data) && isset($data[$param->getName()])) {
                        $params[$key] = $data[$param->getName()];
                    }  else {
                        // exception if not found
                        $property = $ref->getProperty($param->getName());

                        if ($property->isPublic()) {
                            $params[$key] = $data->{$param->getName()};
                        } elseif ($ref->hasMethod($getter = 'get'.ucfirst($param->getName()))) {
                            $params[$key] = $data->{$getter}();
                        } elseif ($ref->hasMethod($getter = $param->getName())) {
                            $params[$key] = $data->{$getter}();
                        }
                    }
                } catch (\ReflectionException $exception) {
                    continue;
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