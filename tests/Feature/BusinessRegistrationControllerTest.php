<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use App\Services\AWSCognitoService;

class BusinessRegistrationControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(); // skip auth/middleware for test
    }

    public function test_database_connection_is_test_db()
{
    $currentDatabase = \DB::connection()->getDatabaseName();

    $this->assertEquals('bar_test', $currentDatabase, "Connected to wrong database: $currentDatabase");
}


    public function test_business_registration_success()
    {

        
        Storage::fake('s3');

        // Mocking Cognito service
        $mockCognito = Mockery::mock(AWSCognitoService::class);

        $mockCognito->shouldReceive('registerUser')
            ->once()
            ->andReturn([
                'status' => 200,
                'message' => 'User registered successfully',
                'userDetails' => [
                   'userDetails' => [
    'cognitoId' => '123e4567-e89b-12d3-a456-426614174000'
]
                ],
                'email' => 'test@example.com'
            ]);

        $this->app->instance(AWSCognitoService::class, $mockCognito);

        // Full valid payload
        $payload = [
            'email' => 'testt@example.com',
            'password' => 'Secret123!!',
            'confirmPassword' => 'Secret123!!',
            'businessName' => 'Test Business',
            'abn' => '12345678901',
            'phone' => '0412345678',
            'website' => 'https://testbusiness.com',
            'business_address' => '123 Business St',
            'businessDescription' => 'We do testing',
            'establishedYear' => '2010',
            'coverage_type' => 'state',
            'statesServed' => ['NSW', 'VIC'],
            'servicetypes' => ['cleaning', 'gardening'],
            'insurance_certificate' => UploadedFile::fake()->create('insurance.pdf', 100, 'application/pdf'),
            'selectedFiles' => [
                UploadedFile::fake()->create('doc1.csv', 50),
                UploadedFile::fake()->create('doc2.csv', 60),
            ],
            'photos' => [
                UploadedFile::fake()->image('photo1.jpg'),
                UploadedFile::fake()->image('photo2.jpg'),
            ],
            'logo' => [
                UploadedFile::fake()->image('logo1.png'),
                UploadedFile::fake()->image('logo2.png'),
            ],
        ];

        $response = $this->postJson('/api/business-register', $payload);
 
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'success',
                    'data' => ['user_id'],
                    'message',
                ]
            ]);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
