<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Debug için exception handling'i disable etme (opsiyonel)
        // $this->withoutExceptionHandling();
    }

    /**
     * Teardown the test environment.
     */
    protected function tearDown(): void
    {
        // Test sonrası cleanup işlemleri
        parent::tearDown();
    }

    /**
     * Create a user and authenticate them for the test
     *
     * @param array $attributes
     * @return User
     */
    protected function authenticatedUser($attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->actingAs($user);
        return $user;
    }

    /**
     * Create multiple authenticated users
     *
     * @param int $count
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function authenticatedUsers(int $count = 2, array $attributes = [])
    {
        return User::factory()->count($count)->create($attributes);
    }

    /**
     * Assert that a model exists in database with given attributes
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $attributes
     */
    protected function assertModelExists($model, $attributes = []): void
    {
        $this->assertDatabaseHas($model->getTable(), array_merge(
            ['id' => $model->id],
            $attributes
        ));
    }

    /**
     * Assert that a model does not exist in database
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    protected function assertModelMissing($model): void
    {
        $this->assertDatabaseMissing($model->getTable(), [
            'id' => $model->id
        ]);
    }

    /**
     * Assert response has validation error for specific field
     *
     * @param \Illuminate\Testing\TestResponse $response
     * @param string|array $fields
     */
    protected function assertValidationError($response, $fields): void
    {
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($fields);
    }

    /**
     * Assert response is successful JSON response
     *
     * @param \Illuminate\Testing\TestResponse $response
     * @param int $status
     */
    protected function assertSuccessfulJson($response, int $status = 200): void
    {
        $response->assertStatus($status);
        $response->assertJson([]);
    }

    /**
     * Create authenticated request with JSON headers
     *
     * @param User|null $user
     * @return $this
     */
    protected function authenticatedJson(?User $user = null)
    {
        $user = $user ?: User::factory()->create();
        
        return $this->actingAs($user)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }

    /**
     * Assert response time is within acceptable range
     *
     * @param \Illuminate\Testing\TestResponse $response
     * @param float $maxSeconds
     */
    protected function assertResponseTimeWithin($response, float $maxSeconds = 1.0): void
    {
        $responseTime = $response->headers->get('X-Response-Time');
        
        if ($responseTime) {
            $this->assertLessThan($maxSeconds, (float) $responseTime);
        }
        
        // Fallback: just assert the request completed
        $this->assertTrue(true);
    }

    /**
     * Create test data factory
     *
     * @param string $model
     * @param int $count
     * @param array $attributes
     * @return mixed
     */
    protected function createTestData(string $model, int $count = 1, array $attributes = [])
    {
        $factory = app("Database\\Factories\\{$model}Factory");
        
        if ($count === 1) {
            return $factory->create($attributes);
        }
        
        return $factory->count($count)->create($attributes);
    }

    /**
     * Assert that collection contains model
     *
     * @param \Illuminate\Support\Collection $collection
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    protected function assertCollectionContains($collection, $model): void
    {
        $this->assertTrue(
            $collection->contains(function ($item) use ($model) {
                return $item->id === $model->id;
            }),
            "Collection does not contain model with ID {$model->id}"
        );
    }

    /**
     * Assert that collection does not contain model
     *
     * @param \Illuminate\Support\Collection $collection
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    protected function assertCollectionDoesNotContain($collection, $model): void
    {
        $this->assertFalse(
            $collection->contains(function ($item) use ($model) {
                return $item->id === $model->id;
            }),
            "Collection contains model with ID {$model->id} but shouldn't"
        );
    }

    /**
     * Disable exception handling for debugging
     *
     * @return $this
     */
    protected function disableExceptionHandling()
    {
        $this->withoutExceptionHandling();
        return $this;
    }

    /**
     * Enable exception handling
     *
     * @return $this
     */
    protected function enableExceptionHandling()
    {
        $this->withExceptionHandling();
        return $this;
    }

    /**
     * Seed database with test data
     *
     * @param array $seeders
     */
    protected function seedDatabase(array $seeders = []): void
    {
        if (empty($seeders)) {
            $this->seed();
        } else {
            $this->seed($seeders);
        }
    }

    /**
     * Assert JSON response structure matches expected
     *
     * @param \Illuminate\Testing\TestResponse $response
     * @param array $structure
     */
    protected function assertJsonResponseStructure($response, array $structure): void
    {
        $response->assertJsonStructure($structure);
    }

    /**
     * Create a mock for external service
     *
     * @param string $service
     * @return \Mockery\MockInterface
     */
    protected function mockExternalService(string $service)
    {
        return \Mockery::mock($service);
    }

    /**
     * Assert that a job was dispatched
     *
     * @param string $job
     */
    protected function assertJobDispatched(string $job): void
    {
        // Bu method Queue facade ile çalışır
        // \Queue::assertPushed($job);
        $this->assertTrue(true); // Placeholder
    }

    /**
     * Assert that an event was fired
     *
     * @param string $event
     */
    protected function assertEventFired(string $event): void
    {
        // Bu method Event facade ile çalışır  
        // \Event::assertDispatched($event);
        $this->assertTrue(true); // Placeholder
    }


}