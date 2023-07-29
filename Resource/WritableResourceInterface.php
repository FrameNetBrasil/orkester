<?php

namespace Orkester\Resource;

interface WritableResourceInterface extends ResourceInterface
{
    public function insert(array $data): int|string;

    public function update(array $data, int|string $key): int|string;

    public function upsert(array $data): int|string;

    public function delete(int|string $key): bool;
}
