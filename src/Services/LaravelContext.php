<?php

namespace Mmstewart\LaravelXRay\Services;

class LaravelContext
{
    public function __construct(
        public string $installedVersion,
        public string $currentBranch,
        public string $targetBranch,
    ) {}
}
