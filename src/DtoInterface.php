<?php

/*
 * This file is part of the Laradev project
 * (c) Darras Florian florian@laradev.ca
 */

namespace Laradev\Dto;

interface DtoInterface
{
    public static function create(array|object $data, ...$arg): self;
    public function fromArray(array $data): self;
    public function fromObject(object $object): self;
    public function toArray(): array;
    public function toJson(): string;
    public function clone(): self;
}
