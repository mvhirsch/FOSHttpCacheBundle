<?php

/*
 * This file is part of the FOSHttpCacheBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\HttpCacheBundle\Tests\Unit\EventListener;

use FOS\HttpCache\ResponseTagger;
use FOS\HttpCache\UserContext\HashGenerator;
use FOS\HttpCacheBundle\EventListener\UserContextListener;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class UserContextListenerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testMisconfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UserContextListener(
            \Mockery::mock(RequestMatcherInterface::class),
            \Mockery::mock(HashGenerator::class),
            null,
            null,
            [
                'user_hash_header' => '',
            ]
        );
    }

    public function testOnKernelRequest(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');

        $requestMatcher = $this->getRequestMatcher($request, true);
        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->once()->andReturn('hash');
        $responseTagger = \Mockery::mock(ResponseTagger::class);
        $responseTagger->shouldReceive('addTags')->with(['fos_http_cache_hashlookup-hash']);

        $userContextListener = new UserContextListener(
            $requestMatcher,
            $hashGenerator,
            null,
            $responseTagger,
            [
                'user_identifier_headers' => ['X-SessionId'],
                'user_hash_header' => 'X-Hash',
            ]
        );
        $event = $this->getKernelRequestEvent($request);

        $userContextListener->onKernelRequest($event);

        $response = $event->getResponse();

        $this->assertNotNull($response);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('hash', $response->headers->get('X-Hash'));
        $this->assertNull($response->headers->get('Vary'));
        $this->assertEquals('max-age=0, no-cache, private', $response->headers->get('Cache-Control'));
    }

    public function testOnKernelRequestNonMaster(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');

        $requestMatcher = $this->getRequestMatcher($request, true);
        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->never();
        $responseTagger = \Mockery::mock(ResponseTagger::class);
        $responseTagger->shouldReceive('addTags')->never();

        $userContextListener = new UserContextListener(
            $requestMatcher,
            $hashGenerator,
            null,
            $responseTagger,
            [
                'user_identifier_headers' => ['X-SessionId'],
                'user_hash_header' => 'X-Hash',
            ]
        );
        $event = $this->getKernelRequestEvent($request, HttpKernelInterface::SUB_REQUEST);

        $userContextListener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnKernelRequestCached(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');

        $requestMatcher = $this->getRequestMatcher($request, true);
        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->once()->andReturn('hash');
        $responseTagger = \Mockery::mock(ResponseTagger::class);
        $responseTagger->shouldReceive('addTags')->with(['fos_http_cache_hashlookup-hash']);

        $userContextListener = new UserContextListener(
            $requestMatcher,
            $hashGenerator,
            null,
            $responseTagger,
            [
                'user_identifier_headers' => ['X-SessionId'],
                'user_hash_header' => 'X-Hash',
                'ttl' => 30,
            ]
        );
        $event = $this->getKernelRequestEvent($request);

        $userContextListener->onKernelRequest($event);

        $response = $event->getResponse();

        $this->assertNotNull($response);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('hash', $response->headers->get('X-Hash'));
        $this->assertEquals('X-SessionId', $response->headers->get('Vary'));
        $this->assertEquals('max-age=30, public', $response->headers->get('Cache-Control'));
    }

    public function testOnKernelRequestNotMatched(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');

        $requestMatcher = $this->getRequestMatcher($request, false);
        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->never();
        $responseTagger = \Mockery::mock(ResponseTagger::class);
        $responseTagger->shouldReceive('addTags')->never();

        $userContextListener = new UserContextListener(
            $requestMatcher,
            $hashGenerator,
            null,
            $responseTagger,
            [
                'user_identifier_headers' => ['X-SessionId'],
                'user_hash_header' => 'X-Hash',
            ]
        );
        $event = $this->getKernelRequestEvent($request);

        $userContextListener->onKernelRequest($event);

        $response = $event->getResponse();

        $this->assertNull($response);
    }

    public function testOnKernelRequestNotMatchedHasHeader(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');
        $request->headers->set('X-Hash', 'hash');

        $requestMatcher = $this->getRequestMatcher($request, false);
        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->never();
        $responseTagger = \Mockery::mock(ResponseTagger::class);
        $responseTagger->shouldReceive('addTags')->never();

        // TODO anonymousRequestMatcher
        $anonymousRequestMatcher = \Mockery::mock(RequestMatcherInterface::class);
        $anonymousRequestMatcher->shouldReceive('matches')->once()->andReturn(true);

        $userContextListener = new UserContextListener(
            $requestMatcher,
            $hashGenerator,
            $anonymousRequestMatcher,
            $responseTagger,
            [
                'user_identifier_headers' => ['X-SessionId'],
                'user_hash_header' => 'X-Hash',
            ]
        );
        $event = $this->getKernelRequestEvent($request);

        $userContextListener->onKernelRequest($event);

        $response = $event->getResponse();

        $this->assertNull($response);
    }

    public function testOnKernelResponse(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');
        $request->headers->set('X-Hash', 'hash');

        $requestMatcher = $this->getRequestMatcher($request, false);
        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->once()->andReturn('hash');
        $responseTagger = \Mockery::mock(ResponseTagger::class);
        $responseTagger->shouldReceive('addTags')->never();

        $userContextListener = new UserContextListener(
            $requestMatcher,
            $hashGenerator,
            null,
            $responseTagger,
            [
                'user_identifier_headers' => ['X-SessionId'],
                'user_hash_header' => 'X-Hash',
            ]
        );
        $event = $this->getKernelResponseEvent($request);

        $userContextListener->onKernelResponse($event);

        $this->assertTrue($event->getResponse()->headers->has('Vary'), 'Vary header must be set');
        $this->assertStringContainsString('X-Hash', $event->getResponse()->headers->get('Vary'));
    }

    public function testOnKernelResponseSetsNoAutoCacheHeader(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');
        $request->headers->set('X-User-Context-Hash', 'hash');

        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->once()->andReturn('hash');

        $userContextListener = new UserContextListener(
            $this->getRequestMatcher($request, false),
            $hashGenerator
        );
        $event = $this->getKernelResponseEvent($request);

        $userContextListener->onKernelResponse($event);

        $this->assertStringContainsString('X-User-Context-Hash', $event->getResponse()->headers->get('Vary'));
        $this->assertEquals(1, $event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testOnKernelResponseDoesNotSetNoAutoCacheHeaderWhenNoSessionListener(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');
        $request->headers->set('X-User-Context-Hash', 'hash');

        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->once()->andReturn('hash');

        $userContextListener = new UserContextListener(
            $this->getRequestMatcher($request, false),
            $hashGenerator,
            null,
            null,
            [],
            false
        );
        $event = $this->getKernelResponseEvent($request);

        $userContextListener->onKernelResponse($event);

        $this->assertStringContainsString('X-User-Context-Hash', $event->getResponse()->headers->get('Vary'));
        $this->assertFalse($event->getResponse()->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testOnKernelResponseSetsNoAutoCacheHeaderWhenCustomHeader(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');
        $request->headers->set('X-User-Context-Hash', 'hash');

        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->once()->andReturn('hash');

        $userContextListener = new UserContextListener(
            $this->getRequestMatcher($request, false),
            $hashGenerator
        );
        $event = $this->getKernelResponseEvent($request, new Response('', 200, ['Vary' => 'X-User-Context-Hash']));

        $userContextListener->onKernelResponse($event);

        $this->assertEquals(1, $event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testOnKernelResponseSetsNoAutoCacheHeaderWhenCustomHeaderAndNoAddVaryOnHash(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');
        $request->headers->set('X-User-Context-Hash', 'hash');

        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->once()->andReturn('hash');

        $userContextListener = new UserContextListener(
            $this->getRequestMatcher($request, false),
            $hashGenerator,
            null,
            null,
            [
                'add_vary_on_hash' => false,
            ]
        );
        $event = $this->getKernelResponseEvent($request, new Response('', 200, ['Vary' => 'X-User-Context-Hash']));

        $userContextListener->onKernelResponse($event);

        $this->assertEquals(1, $event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testOnKernelResponseDoesNotSetNoAutoCacheHeaderWhenNoCustomHeaderAndNoAddVaryOnHash(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');
        $request->headers->set('X-User-Context-Hash', 'hash');

        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->once()->andReturn('hash');

        $userContextListener = new UserContextListener(
            $this->getRequestMatcher($request, false),
            $hashGenerator,
            null,
            null,
            [
                'add_vary_on_hash' => false,
            ]
        );
        $event = $this->getKernelResponseEvent($request);

        $userContextListener->onKernelResponse($event);

        $this->assertFalse($event->getResponse()->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testOnKernelResponseNotMaster(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');
        $request->headers->set('X-Hash', 'hash');

        $requestMatcher = $this->getRequestMatcher($request, false);
        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->never();

        $userContextListener = new UserContextListener(
            $requestMatcher,
            $hashGenerator,
            null,
            null,
            [
                'user_identifier_headers' => ['X-SessionId'],
                'user_hash_header' => 'X-Hash',
            ]
        );
        $event = $this->getKernelResponseEvent($request, null, HttpKernelInterface::SUB_REQUEST);

        $userContextListener->onKernelResponse($event);

        $this->assertFalse($event->getResponse()->headers->has('Vary'));
    }

    /**
     * If there is no hash in the request, vary on the user identifier.
     */
    public function testOnKernelResponseNotCached(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');

        $requestMatcher = $this->getRequestMatcher($request, false);
        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->never();

        $userContextListener = new UserContextListener(
            $requestMatcher,
            $hashGenerator,
            null,
            null,
            [
                'user_identifier_headers' => ['X-SessionId'],
                'user_hash_header' => 'X-Hash',
            ]
        );
        $event = $this->getKernelResponseEvent($request);

        $userContextListener->onKernelResponse($event);

        $this->assertEquals('X-SessionId', $event->getResponse()->headers->get('Vary'));
    }

    /**
     * If there is no hash in the request, vary on the user identifier.
     */
    public function testFullRequestHashOk(): void
    {
        $request = new Request();
        $request->setMethod('GET');
        $request->headers->set('X-Hash', 'hash');

        $requestMatcher = $this->getRequestMatcher($request, false);
        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->once()->andReturn('hash');
        $responseTagger = \Mockery::mock(ResponseTagger::class);
        $responseTagger->shouldReceive('addTags')->with(['fos_http_cache_usercontext-hash']);

        // onKernelRequest
        $userContextListener = new UserContextListener(
            $requestMatcher,
            $hashGenerator,
            null,
            $responseTagger,
            [
                'user_identifier_headers' => ['X-SessionId'],
                'user_hash_header' => 'X-Hash',
            ]
        );
        $event = $this->getKernelRequestEvent($request);

        $userContextListener->onKernelRequest($event);

        $response = $event->getResponse();

        $this->assertNull($response);

        // onKernelResponse
        $event = $this->getKernelResponseEvent($request);
        $userContextListener->onKernelResponse($event);

        $this->assertStringContainsString('X-Hash', $event->getResponse()->headers->get('Vary'));
    }

    /**
     * If the request is an anonymous one, no hash should be generated/validated.
     */
    public function testFullAnonymousRequestHashNotGenerated(): void
    {
        $request = new Request();
        $request->setMethod('GET');
        $request->headers->set('X-Hash', 'anonymous-hash');

        $requestMatcher = $this->getRequestMatcher($request, false);
        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->never();
        $responseTagger = \Mockery::mock(ResponseTagger::class);
        $responseTagger->shouldReceive('addTags')->never();

        $anonymousRequestMatcher = \Mockery::mock(RequestMatcherInterface::class);
        $anonymousRequestMatcher->shouldReceive('matches')->andReturn(true);

        // onKernelRequest
        $userContextListener = new UserContextListener(
            $requestMatcher,
            $hashGenerator,
            $anonymousRequestMatcher,
            $responseTagger,
            [
                'user_identifier_headers' => ['X-SessionId'],
                'user_hash_header' => 'X-Hash',
            ]
        );
        $event = $this->getKernelRequestEvent($request);

        $userContextListener->onKernelRequest($event);

        $response = $event->getResponse();

        $this->assertNull($response);

        // onKernelResponse
        $event = $this->getKernelResponseEvent($request);
        $userContextListener->onKernelResponse($event);

        $this->assertStringContainsString('X-Hash', $event->getResponse()->headers->get('Vary'));
    }

    /**
     * If there is no hash in the requests but session changed, prevent setting bad cache.
     */
    public function testFullRequestHashChanged(): void
    {
        $request = new Request();
        $request->setMethod('GET');
        $request->headers->set('X-Hash', 'hash');

        $requestMatcher = $this->getRequestMatcher($request, false);
        $hashGenerator = \Mockery::mock(HashGenerator::class);
        $hashGenerator->shouldReceive('generateHash')->andReturn('hash-changed');
        $responseTagger = \Mockery::mock(ResponseTagger::class);
        $responseTagger->shouldReceive('addTags')->never();

        // onKernelRequest
        $userContextListener = new UserContextListener(
            $requestMatcher,
            $hashGenerator,
            null,
            $responseTagger,
            [
                'user_identifier_headers' => ['X-SessionId'],
                'user_hash_header' => 'X-Hash',
            ]
        );
        $event = $this->getKernelRequestEvent($request);

        $userContextListener->onKernelRequest($event);

        $response = $event->getResponse();

        $this->assertNull($response);

        // onKernelResponse
        $event = $this->getKernelResponseEvent($request);
        $userContextListener->onKernelResponse($event);

        $this->assertFalse($event->getResponse()->headers->has('Vary'));
        $this->assertEquals('max-age=0, no-cache, no-store, private, s-maxage=0', $event->getResponse()->headers->get('Cache-Control'));
    }

    protected function getKernelRequestEvent(Request $request, $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent(
            \Mockery::mock(HttpKernelInterface::class),
            $request,
            $type
        );
    }

    protected function getKernelResponseEvent(Request $request, ?Response $response = null, $type = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent(
            \Mockery::mock(HttpKernelInterface::class),
            $request,
            $type,
            $response ?: new Response()
        );
    }

    private function getRequestMatcher(Request $request, bool $match): MockInterface&RequestMatcherInterface
    {
        $requestMatcher = \Mockery::mock(RequestMatcherInterface::class);
        $requestMatcher->shouldReceive('matches')->with($request)->andReturn($match);

        return $requestMatcher;
    }
}
