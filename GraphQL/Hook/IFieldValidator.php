<?php

namespace Orkester\GraphQL\Hook;

interface IFieldValidator
{
    public function validateField(string $field, mixed &$value, array &$errors);
}
