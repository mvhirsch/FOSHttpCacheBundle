<?php

/*
 * This file is part of the FOSHttpCacheBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\HttpCacheBundle\Tests\Functional\EventListener;

use FOS\HttpCache\ProxyClient\Varnish;
use FOS\HttpCacheBundle\Tests\Functional\SessionHelperTrait;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

class SwitchUserListenerTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;
    use SessionHelperTrait;

    private const FIREWALL_NAME = 'secured_area';
    private static bool $overrideService = false;

    public function testSwitchUserCompatibility(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/secured_area/switch_user');
        $this->assertSame('admin', substr($client->getResponse()->getContent(), 0, 2000));

        $client->request('GET', '/secured_area/switch_user?_switch_user=user');
        $client->request('GET', '/secured_area/switch_user');
        $this->assertSame('user', substr($client->getResponse()->getContent(), 0, 2000));

        $client->request('GET', '/secured_area/switch_user?_switch_user=_exit');
        $client->request('GET', '/secured_area/switch_user');
        $this->assertSame('admin', substr($client->getResponse()->getContent(), 0, 2000));
    }

    public function testInvalidateContext(): void
    {
        self::$overrideService = true;

        $mock = \Mockery::mock(Varnish::class);
        $mock->shouldReceive('invalidateTags')
            ->once()
        ;
        $mock->shouldReceive('flush')
            ->once()
            ->andReturn(1)
        ;
        $client = static::createClient();

        $container = $client->getContainer();
        $container->set('fos_http_cache.proxy_client.varnish', $mock);

        $this->loginAsAdmin($client);

        $client->request('GET', '/secured_area/switch_user?_switch_user=user');
    }

    public function loginAsAdmin(KernelBrowser $client): void
    {
        $client->loginUser($this->createAdminUser(), self::FIREWALL_NAME);
    }

    private function createAdminUser(): UserInterface
    {
        return new InMemoryUser('admin', 'admin', ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']);
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        $kernel = parent::createKernel($options);
        \assert($kernel instanceof \AppKernel);
        if (static::$overrideService) {
            $kernel->addServiceOverride('override_varnish_proxy.yml');
        }

        return $kernel;
    }
}
