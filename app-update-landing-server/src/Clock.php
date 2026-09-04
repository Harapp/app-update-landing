<?php

declare(strict_types=1);

namespace App;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
