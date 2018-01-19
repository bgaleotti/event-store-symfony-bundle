<?php
/**
 * prooph (http://getprooph.org/)
 *
 * @see       https://github.com/prooph/event-store-symfony-bundle for the canonical source repository
 * @copyright Copyright (c) 2016 prooph software GmbH (http://prooph-software.com/)
 * @license   https://github.com/prooph/event-store-symfony-bundle/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Prooph\Bundle\EventStore\DependencyInjection\Compiler;

use Prooph\Bundle\EventStore\DependencyInjection\ProophEventStoreExtension;
use Prooph\Bundle\EventStore\Exception\RuntimeException;
use Prooph\Bundle\EventStore\Projection\Projection;
use Prooph\Bundle\EventStore\Projection\ReadModelProjection;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ProjectorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $projectors = $container->findTaggedServiceIds(ProophEventStoreExtension::TAG_PROJECTION);

        foreach ($projectors as $id => $projector) {
            $projectorDefinition = $container->getDefinition($id);

            $reflClass = new ReflectionClass($projectorDefinition->getClass());
            if (! $reflClass->implementsInterface(ReadModelProjection::class) && ! $reflClass->implementsInterface(Projection::class)) {
                throw new RuntimeException(sprintf('Tagged service "%s" must implement "%s" or "%s" ', $id, ReadModelProjection::class, Projection::class));
            }

            $tags = $projectorDefinition->getTag(ProophEventStoreExtension::TAG_PROJECTION);
            foreach ($tags as $tag) {
                if (! isset($tag['projection_name'])) {
                    throw new RuntimeException(sprintf('"projection_name" argument is missing from on "prooph_event_store.projection" tagged service "%s"',
                        $id));
                }

                if (! isset($tag['projection_manager'])) {
                    throw new RuntimeException(sprintf('"projection_manager" argument is missing from on "prooph_event_store.projection" tagged service "%s"',
                        $id));
                }

                if (in_array(ReadModelProjection::class, class_implements($projectorDefinition->getClass()))) {
                    if (! isset($tag['read_model'])) {
                        throw new RuntimeException(sprintf('"read_model" argument is missing from on "prooph_event_store.projection" tagged service "%s"',
                            $id));
                    }

                    $readModelProjectionAlias = sprintf('%s.%s.read_model', ProophEventStoreExtension::TAG_PROJECTION, $tag['projection_name']);

                    $container->setAlias($readModelProjectionAlias, $tag['read_model']);
                    $container->getAlias($readModelProjectionAlias)->setPublic(true);
                }

                // alias definition for using the correct ProjectionManager
                $projectionManagerAlias = sprintf('%s.%s.projection_manager', ProophEventStoreExtension::TAG_PROJECTION, $tag['projection_name']);
                $container->setAlias($projectionManagerAlias, sprintf('prooph_event_store.projection_manager.%s', $tag['projection_manager']));
                $container->getAlias($projectionManagerAlias)->setPublic(true);

                if ($id !== sprintf('%s.%s', ProophEventStoreExtension::TAG_PROJECTION, $tag['projection_name'])) {
                    $container->setAlias(sprintf('%s.%s', ProophEventStoreExtension::TAG_PROJECTION, $tag['projection_name']), $id);
                }
            }
        }
    }
}
