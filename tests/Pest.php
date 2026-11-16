<?php

use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/** @return array<string, mixed> Decoded tests/Fixtures/{$name}.json */
function jsonFixture(string $name): array
{
    return json_decode((string) file_get_contents(fixture("{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
}
