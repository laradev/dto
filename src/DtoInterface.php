<?php

/*
 * This file is part of the Laradev project
 * (c) Darras Florian florian@laradev.ca
 */

namespace Laradev\Dto;

interface DtoInterface
{
    public function setData(array $data): self;
    public function toArray(): array;
    public function toJson(): string;
}
