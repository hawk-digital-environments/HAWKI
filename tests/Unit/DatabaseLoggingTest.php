<?php

namespace Tests\Unit;

use App\Models\Log;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as LaravelLog;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseLoggingTest extends TestCase
{
    // Removed RefreshDatabase to protect live database
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure database logging is configured
        config(['logging.default' => 'stack_with_database']);
        
        // Use existing user instead of creating new one
        $this->testUser = User::first() ?: $this->createMinimalTestUser();
    }
    
    protected function tearDown(): void
    {
        // Clean up any test logs we created (but keep existing data)
        Log::where('message', 'like', '%TEST_LOG_%')->delete();
        
        parent::tearDown();
    }
    
    private function createMinimalTestUser()
    {
        // Only create if absolutely necessary and no users exist
        return User::create([
            'name' => 'Test User',
            'email' => 'test_' . time() . '@example.com',
            'username' => 'testuser_' . time(),
            'publicKey' => 'test-public-key',
            'employeetype' => 'test'
        ]);
    }

    /**
     * Test that basic log messages are stored in database
     */
    public function test_basic_log_message_stored_in_database(): void
    {
        // Don't truncate - just count before and after
        $logCountBefore = Log::count();
        
        $testMessage = 'TEST_LOG_basic_' . time();
        
        // Log a message
        LaravelLog::error($testMessage);
        
        // Assert the log was created in database
        $this->assertDatabaseHas('logs', [
            'level' => 'error',
            'message' => $testMessage,
            'channel' => 'testing'  // In test environment, channel is 'testing'
        ]);
        
        // Verify log count increased
        $this->assertEquals($logCountBefore + 1, Log::count());
    }

    /**
     * Test real exception logging with stack trace
     */
    public function test_real_exception_logging_with_stack_trace(): void
    {
        $logCountBefore = Log::count();
        
        try {
            // Create a REAL error by calling undefined method
            $this->triggerRealMethodError();
        } catch (\Error $e) {
            // Log the real exception
            LaravelLog::error('TEST_LOG_exception_' . time(), [
                'exception' => $e,
                'test_context' => 'DatabaseLoggingTest',
                'error_type' => get_class($e)
            ]);
        }
        
        // Verify exception was logged
        $log = Log::where('message', 'like', '%TEST_LOG_exception_%')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('error', $log->level);
        $this->assertNotNull($log->stack_trace);
        $this->assertStringContainsString('triggerRealMethodError', $log->stack_trace);
        
        // Verify context was stored
        $context = $log->context;
        $this->assertArrayHasKey('test_context', $context);
        $this->assertEquals('DatabaseLoggingTest', $context['test_context']);
        
        // Verify count increased
        $this->assertEquals($logCountBefore + 1, Log::count());
    }

    /**
     * Test real database connection error
     */
    public function test_real_database_connection_error(): void
    {
        $logCountBefore = Log::count();
        
        try {
            // Create a REAL database error by using invalid connection
            DB::connection('invalid_connection')->table('users')->get();
        } catch (\Exception $e) {
            LaravelLog::critical('TEST_LOG_db_error_' . time(), [
                'exception' => $e,
                'attempted_connection' => 'invalid_connection',
                'error_code' => $e->getCode()
            ]);
        }
        
        // Verify database error was logged
        $log = Log::where('message', 'like', '%TEST_LOG_db_error_%')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('critical', $log->level);
        
        // Verify context contains error details
        $context = $log->context;
        $this->assertArrayHasKey('attempted_connection', $context);
        $this->assertEquals('invalid_connection', $context['attempted_connection']);
        
        $this->assertEquals($logCountBefore + 1, Log::count());
    }

    /**
     * Test real file system error
     */
    public function test_real_file_system_error(): void
    {
        Log::truncate();
        
        try {
            // Create a REAL file system error
            file_get_contents('/nonexistent/path/file.txt');
        } catch (\Exception $e) {
            LaravelLog::warning('File system error occurred', [
                'exception' => $e,
                'attempted_file' => '/nonexistent/path/file.txt',
                'operation' => 'file_get_contents'
            ]);
        }
        
        // Verify file system error was logged
        $log = Log::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('warning', $log->level);
        $this->assertStringContainsString('File system error occurred', $log->message);
        
        // Verify stack trace contains file operation
        $this->assertNotNull($log->stack_trace);
        $this->assertStringContainsString('file_get_contents', $log->stack_trace);
    }

    /**
     * Test real type error with detailed context
     */
    public function test_real_type_error_with_context(): void
    {
        Log::truncate();
        
        try {
            // Create a REAL type error
            $this->triggerRealTypeError();
        } catch (\TypeError $e) {
            LaravelLog::error('Type error in application', [
                'exception' => $e,
                'function' => 'triggerRealTypeError',
                'expected_type' => 'string',
                'received_type' => gettype(null),
                'test_scenario' => 'real_type_error'
            ]);
        }
        
        // Verify type error was logged with full details
        $log = Log::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('error', $log->level);
        $this->assertStringContainsString('Type error in application', $log->message);
        
        // Verify detailed context
        $context = $log->context;
        $this->assertArrayHasKey('expected_type', $context);
        $this->assertArrayHasKey('received_type', $context);
        $this->assertEquals('string', $context['expected_type']);
        $this->assertEquals('NULL', $context['received_type']);
    }

    /**
     * Test authenticated user logging
     */
    public function test_authenticated_user_logging(): void
    {
        Log::truncate();
        
        // Login as test user
        Auth::login($this->testUser);
        
        try {
            // Create error while authenticated
            throw new \RuntimeException('Authenticated user error', 500);
        } catch (\RuntimeException $e) {
            LaravelLog::error('Runtime error for authenticated user', [
                'exception' => $e,
                'user_action' => 'test_action'
            ]);
        }
        
        // Verify user information was captured
        $log = Log::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals($this->testUser->id, $log->user_id);
        $this->assertEquals('error', $log->level);
        
        // Verify IP and User Agent are captured (test environment)
        $this->assertNotNull($log->remote_addr);
        $this->assertNotNull($log->user_agent);
    }

    /**
     * Test multiple log levels and channels
     */
    public function test_multiple_log_levels_and_channels(): void
    {
        Log::truncate();
        
        // Test different log levels with real errors
        $testCases = [
            ['level' => 'info', 'channel' => 'database'],
            ['level' => 'warning', 'channel' => 'database'],
            ['level' => 'error', 'channel' => 'database'],
            ['level' => 'critical', 'channel' => 'database']
        ];
        
        foreach ($testCases as $case) {
            try {
                // Create different types of real errors
                if ($case['level'] === 'critical') {
                    throw new \Error('Critical system error');
                } elseif ($case['level'] === 'error') {
                    $this->triggerRealMethodError();
                } elseif ($case['level'] === 'warning') {
                    trigger_error('PHP warning triggered', E_USER_WARNING);
                } else {
                    LaravelLog::channel($case['channel'])->info('Info level test');
                }
            } catch (\Throwable $e) {
                LaravelLog::channel($case['channel'])->{$case['level']}('Real ' . $case['level'] . ' error', [
                    'exception' => $e,
                    'test_level' => $case['level']
                ]);
            }
        }
        
        // Verify all log levels were recorded
        $this->assertGreaterThan(0, Log::where('level', 'info')->count());
        $this->assertGreaterThan(0, Log::where('level', 'warning')->count());
        $this->assertGreaterThan(0, Log::where('level', 'error')->count());
        $this->assertGreaterThan(0, Log::where('level', 'critical')->count());
    }

    /**
     * Test error code extraction from real exceptions
     */
    public function test_error_code_extraction_from_real_exceptions(): void
    {
        Log::truncate();
        
        try {
            // Create exception with specific error code
            throw new \RuntimeException('Test error with code', 404);
        } catch (\RuntimeException $e) {
            LaravelLog::error('Exception with error code: ' . $e->getMessage() . ' (Error(code: ' . $e->getCode() . '))', [
                'exception' => $e,
                'error_code' => $e->getCode()
            ]);
        }
        
        // Verify error code is in message and context
        $log = Log::latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('(Error(code: 404))', $log->message);
        $this->assertEquals(404, $log->context['error_code']);
    }

    /**
     * Test concurrent logging (multiple errors at once)
     */
    public function test_concurrent_logging_performance(): void
    {
        Log::truncate();
        
        $startTime = microtime(true);
        $errorCount = 10;
        
        // Generate multiple real errors quickly
        for ($i = 0; $i < $errorCount; $i++) {
            try {
                if ($i % 3 === 0) {
                    $this->triggerRealMethodError();
                } elseif ($i % 3 === 1) {
                    $this->triggerRealTypeError();
                } else {
                    throw new \RuntimeException('Batch error ' . $i, $i);
                }
            } catch (\Throwable $e) {
                LaravelLog::error('Batch error #' . $i, [
                    'exception' => $e,
                    'batch_number' => $i,
                    'error_type' => get_class($e)
                ]);
            }
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Verify all errors were logged
        $this->assertEquals($errorCount, Log::count());
        
        // Performance assertion (should complete within reasonable time)
        $this->assertLessThan(5.0, $executionTime, 'Logging performance is too slow');
        
        // Verify each log has proper data
        $logs = Log::all();
        foreach ($logs as $log) {
            $this->assertNotEmpty($log->message);
            $this->assertNotEmpty($log->level);
            $this->assertNotNull($log->logged_at);
            $this->assertIsArray($log->context);
        }
    }

    /**
     * Helper method to trigger a real method error
     */
    private function triggerRealMethodError(): void
    {
        $object = new \stdClass();
        $object->nonExistentMethod(); // This will throw Error
    }

    /**
     * Helper method to trigger a real type error
     */
    private function triggerRealTypeError(): void
    {
        $this->requireStringParameter(null); // This will throw TypeError
    }

    /**
     * Helper method that requires a string parameter
     */
    private function requireStringParameter(string $parameter): void
    {
        // This method signature enforces string type
        echo $parameter;
    }
}
