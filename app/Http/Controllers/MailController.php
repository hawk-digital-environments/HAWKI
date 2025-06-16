<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeMail;

class MailController extends Controller
{
    /// Dispatch Email Job (check SendEmailJob.php)
    public function sendWelcomeEmail($user)
    {
        $emailData = [
            'user' => $user,
            'message' => 'Welcome to our platform!',
        ];

        $subjectLine = 'Welcome to Our App!';
        $viewTemplate = 'emails.welcome';

        // Dispatch the email job to the queue
        SendEmailJob::dispatch($emailData, $user->email, $subjectLine, $viewTemplate)
                    ->onQueue('emails');  // Optional: specify a queue name
    }

    /**
     * Generate and send OTP to user's email
     */
    public function sendOTP(Request $request): JsonResponse
    {
        try {
            $userInfo = json_decode(Session::get('authenticatedUserInfo'), true);
            $email = $request->input('email', $userInfo['email'] ?? null);
            $username = $userInfo['username'] ?? 'User';
            $appName = config('app.name', 'HAWKI');

            if (!$email) {
                return response()->json([
                    'success' => false,
                    'error' => 'No email address available'
                ], 400);
            }

            // Generate 6-digit OTP
            $otp = $this->generateOTP();
            
            // Store OTP in session with expiration (5 minutes)
            Session::put('otp_code', $otp);
            Session::put('otp_email', $email);
            Session::put('otp_expires_at', now()->addMinutes(5));

            Log::info('Generated OTP for user: ' . $username . ' - ' . $otp);

            // Create HTML email content
            $htmlContent = $this->generateOTPEmailHTML($appName, $username, $otp);

            // Send OTP email
            Mail::send([], [], function ($message) use ($email, $username, $otp, $appName, $htmlContent) {
                $message->to($email)
                       ->subject($appName . ' Log-In Code: ' . $otp)
                       ->from(config('mail.from.address', 'noreply@hawki.local'), $appName . ' Security')
                       ->html($htmlContent);
            });

            Log::info('OTP email sent successfully to: ' . $email);

            return response()->json([
                'success' => true,
                'message' => 'OTP wurde an ' . $email . ' gesendet',
                'expires_in_minutes' => 5
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send OTP: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Fehler beim Senden des OTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate HTML content for OTP email
     */
    private function generateOTPEmailHTML(string $appName, string $username, string $otp): string
    {
        // Generate the logo URL properly in PHP
        $logoUrl = route('system.image', ['name' => 'logo_svg']);
        
        return '
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $appName . ' OTP</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #f8f9fa;
                }
                .container {
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .header h1 {
                    color: #2c3e50;
                    margin: 0;
                    font-size: 28px;
                    font-weight: 600;
                }
                .logo {
                    height: 60px;
                    margin-bottom: 20px;
                }
                .otp-container {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px;
                    border-radius: 8px;
                    text-align: center;
                    margin: 30px 0;
                }
                .otp-label {
                    font-size: 16px;
                    margin-bottom: 10px;
                    opacity: 0.9;
                }
                .otp-code {
                    font-size: 36px;
                    font-weight: bold;
                    letter-spacing: 8px;
                    font-family: "Courier New", monospace;
                    margin: 10px 0;
                }
                .validity {
                    font-size: 14px;
                    opacity: 0.8;
                    margin-top: 10px;
                }
                .warning {
                    background-color: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 6px;
                    padding: 15px;
                    margin: 20px 0;
                    color: #856404;
                }
                .info {
                    background-color: #d1ecf1;
                    border: 1px solid #bee5eb;
                    border-radius: 6px;
                    padding: 15px;
                    margin: 20px 0;
                    color: #0c5460;
                }
                .footer {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #eee;
                    text-align: center;
                    color: #666;
                    font-size: 14px;
                }
                .timestamp {
                    font-size: 12px;
                    color: #888;
                    margin-top: 10px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <img src="' . $logoUrl . '" alt="' . $appName . ' Logo" class="logo">
                    <h1>' . $appName . ' Log-in Code</h1>
                </div>
                
                <p>Hallo <strong>' . htmlspecialchars($username) . '</strong>,</p>
                
                <p>Sie haben einen Log-in Code für Ihren ' . $appName . ' Account angefordert.</p>
                
                <div class="otp-container">
                    <div class="otp-label">Der Log-in Code lautet:</div>
                    <div class="otp-code">' . $otp . '</div>
                    <div class="validity">Gültig für 5 Minuten</div>
                </div>
                
                <div>
                    <strong>Hinweis:</strong> Geben Sie diesen Code in der Anwendung ein, um fortzufahren.
                </div>
                
                <div>
                    <strong>Sicherheitshinweis:</strong> Falls Sie diese E-Mail nicht angefordert haben, ignorieren Sie sie bitte. Teilen Sie diesen Code niemals mit anderen.
                </div>
                
                <div class="footer">
                    <p>Mit freundlichen Grüßen,<br>
                    Ihr <strong>' . $appName . '</strong> Team</p>
                    
                    <div class="timestamp">
                        Gesendet am: ' . now()->format('d.m.Y H:i:s') . '
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }

    /**
     * Generate a 6-digit OTP
     */
    private function generateOTP(): string
    {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function isHerdEnvironment(): bool
    {
        return str_contains(gethostname(), '.herd') || 
               str_contains($_SERVER['SERVER_NAME'] ?? '', '.test');
    }
}
