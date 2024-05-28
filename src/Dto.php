<?php

/*
 * This file is part of the Laradev project
 * (c) Darras Florian florian@laradev.ca
 */

namespace Laradev\Dto;

abstract class Dto implements DtoInterface
{
    protected array $initialized_properties = [];

    public function setData(array $data): self
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

    private function extractValueFromType(\ReflectionType $type, mixed $data): mixed
    {
        if ($type->isBuiltin()) {
            return $data;
        }

        $class = (string) $type;
        $ref = new \ReflectionClass($class);

        if (is_object($data) && $data::class === $class) {
            return $data;
        }

        if ($ref->isEnum()) {
            return $class::tryFrom($data);
        }

        if (!is_array($data)) {
            return $data;
        }

        if (in_array(DtoInterface::class, array_keys($ref->getInterfaces()))) {
            return (new $class())->setData($data);
        }

        $constructor = $ref->getConstructor();

        if (null !== $constructor && count($constructor->getParameters()) > 0) {
            $params = [];

            foreach ($constructor->getParameters() as $key => $param) {
                if (isset($data[$param->getName()])) {
                    $params[$key] = $data[$param->getName()];
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