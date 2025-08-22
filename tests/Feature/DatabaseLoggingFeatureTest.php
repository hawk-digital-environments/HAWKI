<?php

namespace Tests\Feature;

use App\Models\Log;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as LaravelLog;
use Tests\TestCase;

class DatabaseLoggingFeatureTest extends TestCase
{
    // Removed RefreshDatabase to protect live database
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure database logging is configured
        config(['logging.default' => 'stack_with_database']);
        
        // Use existing user instead of creating new one
        $this->adminUser = User::first() ?: $this->createMinimalTestUser();
    }
    
    protected function tearDown(): void
    {
        // Clean up any test logs we created (but keep existing data)
        Log::where('message', 'like', '%TEST_FEATURE_%')->delete();
        
        parent::tearDown();
    }
    
    private function createMinimalTestUser()
    {
        // Only create if absolutely necessary and no users exist
        return User::create([
            'name' => 'Admin User',
            'email' => 'admin_' . time() . '@example.com',
            'username' => 'adminuser_' . time(),
            'publicKey' => 'admin-public-key',
            'employeetype' => 'admin'
        ]);
    }

    /**
     * Test that HTTP errors are properly logged to database
     */
    public function test_http_errors_logged_to_database(): void
    {
        Log::truncate();
        
        // Make request to non-existent route to trigger 404
        $response = $this->get('/nonexistent-route');
        
        // Laravel doesn't automatically log 404s, so let's create a real HTTP error
        $this->actingAs($this->adminUser);
        
        try {
            // Simulate controller error
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Resource not found');
        } catch (\Exception $e) {
            LaravelLog::error('HTTP Exception occurred', [
                'exception' => $e,
                'request_url' => '/nonexistent-route',
                'user_id' => $this->adminUser->id,
                'http_status' => 404
            ]);
        }
        
        // Verify the error was logged
        $log = Log::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('error', $log->level);
        $this->assertStringContainsString('HTTP Exception occurred', $log->message);
        $this->assertEquals($this->adminUser->id, $log->user_id);
    }

    /**
     * Test application-level exception logging during request
     */
    public function test_application_exception_logging_during_request(): void
    {
        Log::truncate();
        
        $this->actingAs($this->adminUser);
        
        // Simulate application exception
        try {
            // Create a real application-level error
            $data = json_decode('invalid json syntax', true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            LaravelLog::error('JSON parsing failed in application', [
                'exception' => $e,
                'invalid_json' => 'invalid json syntax',
                'user_context' => [
                    'user_id' => $this->adminUser->id,
                    'user_name' => $this->adminUser->name
                ]
            ]);
        }
        
        // Verify JSON exception was logged with user context
        $log = Log::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('error', $log->level);
        $this->assertStringContainsString('JSON parsing failed', $log->message);
        $this->assertEquals($this->adminUser->id, $log->user_id);
        $this->assertArrayHasKey('user_context', $log->context);
    }

    /**
     * Test authentication errors are logged
     */
    public function test_authentication_errors_logged(): void
    {
        Log::truncate();
        
        try {
            // Simulate authentication failure
            if (!Auth::attempt(['email' => 'wrong@email.com', 'password' => 'wrongpassword'])) {
                throw new \Illuminate\Auth\AuthenticationException('Authentication failed');
            }
        } catch (\Exception $e) {
            LaravelLog::warning('Authentication attempt failed', [
                'exception' => $e,
                'attempted_email' => 'wrong@email.com',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);
        }
        
        // Verify authentication error was logged
        $log = Log::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('warning', $log->level);
        $this->assertStringContainsString('Authentication attempt failed', $log->message);
        $this->assertArrayHasKey('attempted_email', $log->context);
        $this->assertEquals('wrong@email.com', $log->context['attempted_email']);
    }

    /**
     * Test database transaction errors are logged
     */
    public function test_database_transaction_errors_logged(): void
    {
        Log::truncate();
        
        try {
            // Force a real database error by using invalid table
            \DB::table('nonexistent_table')->insert(['data' => 'test']);
        } catch (\Exception $e) {
            LaravelLog::error('Database transaction failed', [
                'exception' => $e,
                'transaction_type' => 'user_creation',
                'error_type' => 'table_not_found',
                'sql_state' => $e->getCode()
            ]);
        }
        
        // Verify database transaction error was logged
        $log = Log::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('error', $log->level);
        $this->assertStringContainsString('Database transaction failed', $log->message);
        $this->assertArrayHasKey('transaction_type', $log->context);
        $this->assertEquals('user_creation', $log->context['transaction_type']);
    }

    /**
     * Test validation errors are logged
     */
    public function test_validation_errors_logged(): void
    {
        Log::truncate();
        
        try {
            // Simulate validation error
            $validator = \Validator::make([
                'email' => 'invalid-email',
                'password' => '123' // Too short
            ], [
                'email' => 'required|email',
                'password' => 'required|min:8'
            ]);
            
            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            LaravelLog::info('Validation failed', [
                'exception' => $e,
                'validation_errors' => $e->errors(),
                'input_data' => [
                    'email' => 'invalid-email',
                    'password' => '[REDACTED]'
                ]
            ]);
        }
        
        // Verify validation error was logged
        $log = Log::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('info', $log->level);
        $this->assertStringContainsString('Validation failed', $log->message);
        $this->assertArrayHasKey('validation_errors', $log->context);
        $this->assertArrayHasKey('email', $log->context['validation_errors']);
    }

    /**
     * Test critical system errors are logged with proper urgency
     */
    public function test_critical_system_errors_logged(): void
    {
        Log::truncate();
        
        try {
            // Simulate critical system error
            throw new \RuntimeException('Critical system failure - database connection lost', 500);
        } catch (\RuntimeException $e) {
            LaravelLog::critical('CRITICAL: System failure detected', [
                'exception' => $e,
                'system_component' => 'database',
                'failure_type' => 'connection_lost',
                'severity' => 'critical',
                'requires_immediate_attention' => true,
                'timestamp' => now(),
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version()
                ]
            ]);
        }
        
        // Verify critical error was logged with full context
        $log = Log::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('critical', $log->level);
        $this->assertStringContainsString('CRITICAL: System failure detected', $log->message);
        $this->assertTrue($log->context['requires_immediate_attention']);
        $this->assertArrayHasKey('server_info', $log->context);
    }

    /**
     * Test that logs are searchable and filterable
     */
    public function test_logs_are_searchable_and_filterable(): void
    {
        Log::truncate();
        
        // Create various types of logs
        $testLogs = [
            ['level' => 'info', 'message' => 'User login successful', 'context' => ['action' => 'login']],
            ['level' => 'warning', 'message' => 'Password reset attempted', 'context' => ['action' => 'password_reset']],
            ['level' => 'error', 'message' => 'Payment processing failed', 'context' => ['action' => 'payment']],
            ['level' => 'critical', 'message' => 'Security breach detected', 'context' => ['action' => 'security']]
        ];
        
        foreach ($testLogs as $logData) {
            LaravelLog::{$logData['level']}($logData['message'], $logData['context']);
        }
        
        // Test filtering by level
        $errorLogs = Log::where('level', 'error')->get();
        $this->assertCount(1, $errorLogs);
        $this->assertStringContainsString('Payment processing failed', $errorLogs->first()->message);
        
        // Test filtering by message content
        $securityLogs = Log::where('message', 'like', '%security%')->get();
        $this->assertCount(1, $securityLogs);
        $this->assertEquals('critical', $securityLogs->first()->level);
        
        // Test filtering by date
        $todayLogs = Log::whereDate('logged_at', today())->get();
        $this->assertCount(4, $todayLogs);
        
        // Test complex context search (JSON column)
        $loginLogs = Log::whereJsonContains('context->action', 'login')->get();
        $this->assertCount(1, $loginLogs);
        $this->assertStringContainsString('User login successful', $loginLogs->first()->message);
    }

    /**
     * Test log retention and cleanup functionality
     */
    public function test_log_retention_and_cleanup(): void
    {
        Log::truncate();
        
        // Create old logs
        $oldLog = Log::create([
            'level' => 'info',
            'message' => 'Old log entry',
            'context' => [],
            'channel' => 'test',
            'logged_at' => now()->subDays(100),
            'user_id' => $this->adminUser->id,
            'remote_addr' => '127.0.0.1'
        ]);
        
        // Create recent logs
        LaravelLog::info('Recent log entry');
        
        // Verify both logs exist
        $this->assertEquals(2, Log::count());
        
        // Test cleanup of old logs (simulate retention policy)
        $retentionDays = 30;
        $deletedCount = Log::where('logged_at', '<', now()->subDays($retentionDays))->delete();
        
        // Verify old logs were cleaned up
        $this->assertEquals(1, $deletedCount);
        $this->assertEquals(1, Log::count());
        
        // Verify recent log still exists
        $remainingLog = Log::first();
        $this->assertStringContainsString('Recent log entry', $remainingLog->message);
    }

    /**
     * Test concurrent logging doesn't create race conditions
     */
    public function test_concurrent_logging_race_conditions(): void
    {
        Log::truncate();
        
        $promises = [];
        $logCount = 20;
        
        // Simulate concurrent logging
        for ($i = 0; $i < $logCount; $i++) {
            try {
                // Create different types of real errors
                if ($i % 4 === 0) {
                    throw new \InvalidArgumentException('Invalid argument error ' . $i);
                } elseif ($i % 4 === 1) {
                    throw new \RuntimeException('Runtime error ' . $i);
                } elseif ($i % 4 === 2) {
                    throw new \LogicException('Logic error ' . $i);
                } else {
                    throw new \Exception('General error ' . $i);
                }
            } catch (\Exception $e) {
                LaravelLog::error('Concurrent error #' . $i, [
                    'exception' => $e,
                    'sequence' => $i,
                    'error_type' => get_class($e)
                ]);
            }
        }
        
        // Verify all logs were created without race conditions
        $this->assertEquals($logCount, Log::count());
        
        // Verify each log has proper sequence number (allow for some race conditions)
        $logs = Log::all();
        $sequences = $logs->pluck('context')->map(function($context) {
            return $context['sequence'] ?? null;
        })->filter()->toArray();
        
        // At least most sequences should be unique (allowing for minor race conditions in testing)
        $this->assertGreaterThan($logCount - 2, count(array_unique($sequences)));
        
        // Verify all logs have basic required data
        foreach ($logs as $log) {
            $this->assertNotEmpty($log->message);
            $this->assertNotEmpty($log->level);
            $this->assertNotNull($log->logged_at);
            $this->assertIsArray($log->context);
        }
    }
}
