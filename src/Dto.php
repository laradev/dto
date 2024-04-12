<?php

/*
 * This file is part of the Laradev project
 * (c) Darras Florian florian@laradev.ca
 */

namespace Laradev\Dto;

abstract class Dto implements DtoInterface
{
    public function setData(array $data): self
    {
        $ref = new \ReflectionClass($this);

        foreach ($ref->getProperties() as $property) {
            $setter = "set".ucfirst($property->getName());
            if ($ref->hasMethod($setter)) {
                $this->$setter($data[$property->getName()]);
            } elseif (isset($data[$property->getName()]) && $property->isPublic()) {
                $this->{$property->getName()} = $data[$property->getName()];
            }
        }

        return $this;
    }

    abstract public function toArray(): array;

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}