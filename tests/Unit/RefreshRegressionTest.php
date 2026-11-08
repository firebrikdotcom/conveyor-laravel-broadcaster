<?php

namespace Kanata\LaravelBroadcaster\Tests\Unit;

use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Kanata\LaravelBroadcaster\Conveyor;
use Kanata\LaravelBroadcaster\ConveyorDriver;
use Kanata\LaravelBroadcaster\Models\Token;
use Kanata\LaravelBroadcaster\Services\JwtToken;
use Kanata\LaravelBroadcaster\Tests\Stubs\User;
use Kanata\LaravelBroadcaster\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RefreshRegressionTest extends TestCase
{
    public function test_laravel_string_channels_reach_the_server(): void
    {
        Http::fake();
        (new ConveyorDriver(new Conveyor()))->broadcast(['public-room', 'private-room'], 'event', ['message' => 'hello']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['channel'] === 'private-room'
            && json_decode($request['message'], true) === ['message' => 'hello']);
    }

    public function test_broadcast_failure_is_visible_to_queue_retry_logic(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $this->expectException(RequestException::class);
        (new Conveyor())->broadcast(['room'], 'event');
    }

    public function test_invalid_payload_does_not_send_corrupted_json(): void
    {
        Http::fake();
        try {
            (new Conveyor())->broadcast(['room'], 'event', ['invalid' => "\xB1\x31"]);
            $this->fail('Invalid JSON must throw.');
        } catch (\JsonException) {
            Http::assertNothingSent();
        }
    }

    public function test_auth_endpoint_failure_does_not_return_an_empty_success(): void
    {
        config(['conveyor.query' => 'token=secret']);
        Http::fake(['*' => Http::response([], 403)]);
        $this->expectException(RequestException::class);
        Conveyor::getToken('private-room');
    }

    public function test_single_use_token_is_removed_and_cannot_be_replayed(): void
    {
        $user = User::create(['name' => 'Once']);
        $token = JwtToken::create(str_repeat('a', 32), (int) $user->getKey(), null, 1);
        $this->assertSame(1, $token->uses);
        $this->assertSame(1, $token->allowed_uses);
        $request = (new ServerRequest('GET', '/'))->withHeader('Authorization', 'Bearer ' . $token->token);
        $this->assertNotNull(JwtToken::getToken($request));
        $this->assertNull(JwtToken::getToken($request));
        $this->assertNull(Token::find($token->getKey()));
    }

    public function test_expired_token_is_rejected_by_http_token_lookup(): void
    {
        $user = User::create(['name' => 'Expired']);
        $token = JwtToken::create(str_repeat('b', 32), (int) $user->getKey(), -60);
        $request = (new ServerRequest('GET', '/'))->withHeader('Authorization', 'Bearer ' . $token->token);
        $this->assertNull(JwtToken::getToken($request));
        $this->assertNull(Token::find($token->getKey()));
    }

    public function test_expired_token_is_rejected_by_connection_validation(): void
    {
        $user = User::create(['name' => 'Expired']);
        $token = JwtToken::create(str_repeat('c', 32), (int) $user->getKey(), -60);
        $this->expectException(AuthorizationException::class);
        (new ConveyorDriver(new Conveyor()))->validateConnection($token->token);
    }

    public function test_nonpositive_use_limit_is_rejected(): void
    {
        $user = User::create(['name' => 'Limit']);
        $this->expectException(\InvalidArgumentException::class);
        JwtToken::create(str_repeat('d', 32), (int) $user->getKey(), null, 0);
    }

    public function test_absent_channel_is_an_authorization_error(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        (new ConveyorDriver(new Conveyor()))->auth(new Request());
    }

    public function test_unauthenticated_response_is_an_authorization_error(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        (new ConveyorDriver(new Conveyor()))->validAuthenticationResponse(new Request(), true);
    }

    public function test_authorization_uses_request_guard_instead_of_default_guard(): void
    {
        $user = User::create(['name' => 'Custom guard']);
        $request = new Request(['channel_name' => 'private-room']);
        $request->setUserResolver(fn () => $user);
        $driver = new ConveyorDriver(new Conveyor());
        $driver->channel('room', fn ($authenticated) => $authenticated->is($user));
        $this->assertArrayHasKey('auth', $driver->auth($request));
        $this->assertSame(1, Token::where('user_id', $user->getKey())->firstOrFail()->uses);
    }
    public function test_stale_single_use_record_cannot_be_consumed_twice(): void
    {
        $user = User::create(['name' => 'Race']);
        $token = JwtToken::create(str_repeat('e', 32), (int) $user->getKey(), null, 1);
        $stale = $token->fresh();
        $token->consume();
        $this->expectException(AuthorizationException::class);
        $stale->consume();
    }

    public function test_stale_multi_use_records_cannot_overrun_the_limit(): void
    {
        $user = User::create(['name' => 'Race']);
        $token = JwtToken::create(str_repeat('f', 32), (int) $user->getKey(), null, 2);
        $second = $token->fresh();
        $third = $token->fresh();
        $token->consume();
        $this->assertSame(1, $token->fresh()->uses);
        $second->consume();
        $this->assertNull($token->fresh());
        $this->expectException(AuthorizationException::class);
        $third->consume();
    }

}
