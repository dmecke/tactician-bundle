<?php

namespace League\Tactician\Bundle\Tests\DependencyInjection\Compiler;

use League\Tactician\Bundle\DependencyInjection\Compiler\CommandHandlerPass;
use League\Tactician\Bundle\DependencyInjection\HandlerMapping\ClassNameMapping;
use League\Tactician\Bundle\DependencyInjection\HandlerMapping\HandlerMapping;
use League\Tactician\Bundle\DependencyInjection\HandlerMapping\Routing;
use League\Tactician\Bundle\Tests\Fake\FakeCommand;
use League\Tactician\Bundle\Tests\Fake\OtherFakeCommand;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CommandHandlerPassTest extends TestCase
{
    /**
     * @var HandlerMapping
     */
    private $mappingStrategy;

    protected function setUp()
    {
        $this->mappingStrategy = new ClassNameMapping();
    }

    public function testAddingSingleDefaultBus()
    {
        $container = $this->containerWithConfig(
            [
                'commandbus' =>
                    [
                        'default' => ['middleware' => []],
                    ]
            ]
        );

        (new CommandHandlerPass($this->mappingStrategy))->process($container);

        $this->assertTrue($container->hasDefinition('tactician.commandbus.default'));

        $this->assertDefaultAliasesAreDeclared($container, 'default');
    }

    public function testProcessAddsLocatorAndHandlerDefinitionForTaggedBuses()
    {
        $container = $this->containerWithConfig(
            [
                'default_bus' => 'custom_bus',
                'commandbus' =>
                    [
                        'default' => ['middleware' => ['one']],
                        'custom_bus' => ['middleware' => ['two']],
                        'other_bus' => ['middleware' => ['three']]
                    ]
            ]
        );

        (new CommandHandlerPass($this->mappingStrategy))->process($container);

        $this->assertTrue($container->hasDefinition('tactician.commandbus.default'));
        $this->assertTrue($container->hasDefinition('tactician.commandbus.custom_bus'));
        $this->assertTrue($container->hasDefinition('tactician.commandbus.other_bus'));

        $this->assertDefaultAliasesAreDeclared($container, 'custom_bus');
    }

    public function test_handler_mapping_is_called()
    {
        $container = $this->containerWithConfig(
            [
                'commandbus' => [ 'default' => ['middleware' => []] ]
            ]
        );

        $routing = new Routing(['default']);
        $routing->routeToAllBuses(FakeCommand::class, 'some.handler');

        $mapping = $this->prophesize(HandlerMapping::class);
        $mapping->build($container, Argument::type(Routing::class))->willReturn($routing);

        (new CommandHandlerPass($mapping->reveal()))->process($container);

        $this->assertEquals(
            [FakeCommand::class => 'some.handler'],
            $container->getDefinition('tactician.commandbus.default.handler.locator')->getArgument(1)
        );
    }

    public function test_handler_mapping_is_kept_bus_specific()
    {
        $container = $this->containerWithConfig(
            [
                'default_bus' => 'bus.a',
                'commandbus' => [
                    'bus.a' => ['middleware' => []],
                    'bus.b' => ['middleware' => []]
                ]
            ]
        );

        $routing = new Routing(['bus.a', 'bus.b']);
        $routing->routeToBus('bus.a', FakeCommand::class, 'some.handler.a');
        $routing->routeToBus('bus.b', FakeCommand::class, 'some.handler.b');
        $routing->routeToAllBuses(OtherFakeCommand::class, 'some.other.handler');

        $mapping = $this->prophesize(HandlerMapping::class);
        $mapping->build($container, Argument::type(Routing::class))->willReturn($routing);

        (new CommandHandlerPass($mapping->reveal()))->process($container);

        $this->assertEquals(
            [FakeCommand::class => 'some.handler.a', OtherFakeCommand::class => 'some.other.handler'],
            $container->getDefinition('tactician.commandbus.bus.a.handler.locator')->getArgument(1)
        );
        $this->assertEquals(
            [FakeCommand::class => 'some.handler.b', OtherFakeCommand::class => 'some.other.handler'],
            $container->getDefinition('tactician.commandbus.bus.b.handler.locator')->getArgument(1)
        );
    }

    private function containerWithConfig($config)
    {
        $container = new ContainerBuilder();

        $container->prependExtensionConfig('tactician', $config);

        return $container;
    }

    /**
     * @param $container
     */
    protected function assertDefaultAliasesAreDeclared(ContainerBuilder $container, string $defaultBusId)
    {
        $this->assertSame(
            $container->findDefinition('tactician.commandbus'),
            $container->getDefinition("tactician.commandbus.$defaultBusId")
        );

        $this->assertSame(
            $container->findDefinition('tactician.handler.locator.symfony'),
            $container->getDefinition("tactician.commandbus.$defaultBusId.handler.locator")
        );

        $this->assertSame(
            $container->findDefinition('tactician.middleware.command_handler'),
            $container->getDefinition("tactician.commandbus.$defaultBusId.middleware.command_handler")
        );
    }
}
