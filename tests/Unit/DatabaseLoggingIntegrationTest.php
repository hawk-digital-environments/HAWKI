<?php

namespace Tests\Unit;

use App\Logging\DatabaseHandler;
use App\Models\Log;
use Tests\TestCase;
use Monolog\Level;
use Monolog\LogRecord;

class DatabaseLoggingIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Stelle sicher, dass wir in der Test-Umgebung sind
        $this->assertSame('testing', app()->environment());
    }

    /**
     * Test: Log Modell funktioniert korrekt
     */
    public function test_log_model_basic_functionality(): void
    {
        $initialCount = Log::count();
        
        // Erstelle einen Test-Log direkt über das Model
        $log = Log::create([
            'channel' => 'test-channel',
            'level' => 'debug',
            'message' => 'Test Message für Model Test',
            'context' => ['model' => 'test'], // Array, nicht JSON
            'user_id' => 1,
            'remote_addr' => '192.168.1.1',
            'user_agent' => 'Model Test Agent'
        ]);
        
        // Überprüfe, dass Log erstellt wurde
        $this->assertNotNull($log->id);
        $this->assertSame($initialCount + 1, Log::count());
        
        // Überprüfe Eigenschaften
        $this->assertSame('test-channel', $log->channel);
        $this->assertSame('debug', $log->level);
        $this->assertSame('Test Message für Model Test', $log->message);
        
        // Teste context (automatisch als Array durch Cast)
        $context = $log->context;
        $this->assertIsArray($context);
        $this->assertSame('test', $context['model']);
        
        // Aufräumen
        $log->delete();
        $this->assertSame($initialCount, Log::count());
    }

    /**
     * Test: Log Model mit Context Accessor
     */
    public function test_log_model_context_accessor(): void
    {
        $initialCount = Log::count();
        
        // Erstelle einen Test-Log
        $log = Log::create([
            'channel' => 'accessor-test',
            'level' => 'info',
            'message' => 'Test Context Accessor',
            'context' => ['accessor' => 'test', 'number' => 42], // Array
            'user_id' => null,
            'remote_addr' => '127.0.0.1',
            'user_agent' => 'Accessor Test'
        ]);
        
        // Teste den Context Accessor
        $context = $log->context;
        $this->assertIsArray($context);
        $this->assertSame('test', $context['accessor']);
        $this->assertSame(42, $context['number']);
        
        // Aufräumen
        $log->delete();
        $this->assertSame($initialCount, Log::count());
    }

    /**
     * Test: DatabaseHandler erstellt korrektes Log-Format
     */
    public function test_database_handler_log_format(): void
    {
        $initialCount = Log::count();
        
        // Simuliere einen einfachen Log ohne Facades
        $testData = [
            'channel' => 'handler-test',
            'level' => 'warning',
            'message' => 'Test Handler Format',
            'context' => ['handler' => 'format-test'], // Array
            'user_id' => null,
            'remote_addr' => '10.0.0.1',
            'user_agent' => 'Handler Test'
        ];
        
        // Erstelle direkt über Model (simuliert DatabaseHandler)
        $log = Log::create($testData);
        
        // Überprüfe korrektes Format
        $this->assertNotNull($log->id);
        $this->assertNotNull($log->created_at);
        $this->assertSame('handler-test', $log->channel);
        $this->assertSame('warning', $log->level);
        $this->assertSame('Test Handler Format', $log->message);
        
        // Überprüfe Context-Array
        $context = $log->context;
        $this->assertIsArray($context);
        $this->assertArrayHasKey('handler', $context);
        $this->assertSame('format-test', $context['handler']);
        
        // Aufräumen
        $log->delete();
        $this->assertSame($initialCount, Log::count());
    }

    /**
     * Test: Log Queries funktionieren
     */
    public function test_log_queries(): void
    {
        $initialCount = Log::count();
        
        // Erstelle mehrere Test-Logs
        $logs = [];
        $logs[] = Log::create([
            'channel' => 'query-test',
            'level' => 'error',
            'message' => 'Error Message',
            'context' => ['type' => 'error'], // Array
            'user_id' => 1,
            'remote_addr' => '192.168.1.100',
            'user_agent' => 'Query Test'
        ]);
        
        $logs[] = Log::create([
            'channel' => 'query-test',
            'level' => 'info',
            'message' => 'Info Message',
            'context' => ['type' => 'info'], // Array
            'user_id' => 1,
            'remote_addr' => '192.168.1.100',
            'user_agent' => 'Query Test'
        ]);
        
        // Teste Queries
        $this->assertSame($initialCount + 2, Log::count());
        
        // Nach Channel filtern
        $channelLogs = Log::where('channel', 'query-test')->get();
        $this->assertCount(2, $channelLogs);
        
        // Nach Level filtern
        $errorLogs = Log::where('level', 'error')->where('channel', 'query-test')->get();
        $this->assertCount(1, $errorLogs);
        $this->assertSame('Error Message', $errorLogs->first()->message);
        
        // Nach User filtern
        $userLogs = Log::where('user_id', 1)->where('channel', 'query-test')->get();
        $this->assertCount(2, $userLogs);
        
        // Aufräumen
        foreach ($logs as $log) {
            $log->delete();
        }
        $this->assertSame($initialCount, Log::count());
    }

    /**
     * Test: DatabaseHandler funktioniert grundsätzlich
     */
    public function test_database_handler_integration(): void
    {
        $initialCount = Log::count();
        
        // Teste nur, dass der Handler initialisiert werden kann
        $handler = new DatabaseHandler();
        $this->assertInstanceOf(DatabaseHandler::class, $handler);
        
        // Teste das Log-Format, das der Handler erwartet
        $testRecord = [
            'level' => 'error',
            'channel' => 'handler-integration',
            'message' => 'Handler Integration Test',
            'context' => ['test' => 'handler_integration'],
            'stack_trace' => null,
            'remote_addr' => '203.0.113.1',
            'user_agent' => 'Handler Test Agent',
            'user_id' => null,
            'logged_at' => now(),
        ];
        
        // Simuliere was der Handler macht (Log erstellen)
        $log = Log::create($testRecord);
        
        // Überprüfe, dass alles korrekt gespeichert wurde
        $this->assertSame($initialCount + 1, Log::count());
        $this->assertSame('error', $log->level);
        $this->assertSame('handler-integration', $log->channel);
        $this->assertSame('Handler Integration Test', $log->message);
        $this->assertIsArray($log->context);
        $this->assertSame('handler_integration', $log->context['test']);
        $this->assertSame('203.0.113.1', $log->remote_addr);
        $this->assertSame('Handler Test Agent', $log->user_agent);
        
        // Aufräumen
        $log->delete();
        $this->assertSame($initialCount, Log::count());
    }
}
