<?php
declare(strict_types=1);

namespace League\Tactician\Bundle\DependencyInjection\HandlerMapping;

use Symfony\Component\DependencyInjection\Definition;

final class ClassNameMapping extends TagBasedMapping
{
    protected function isSupported(Definition $definition, array $tagAttributes): bool
    {
        return isset($tagAttributes['command']) && class_exists($tagAttributes['command']);
    }

    protected function findCommandsForService(Definition $definition, array $tagAttributes): array
    {
        return [
            $tagAttributes['command']
        ];
    }
}
