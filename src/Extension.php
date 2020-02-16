<?php

declare(strict_types=1);

namespace Bolt\BoltForms;

use Bolt\Extension\BaseExtension;

class Extension extends BaseExtension
{
    public function getName(): string
    {
        return 'Boltforms';
    }

    public function initialize(): void
    {
        $this->addTwigNamespace('boltforms');
    }
}
