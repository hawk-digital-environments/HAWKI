<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeMail;
use App\Mail\OTPMail;

class MailController extends Controller
{
    /// Send Welcome Email using the WelcomeMail Mailable
    public function sendWelcomeEmail($user)
    {
        try {
            // Send welcome email using the WelcomeMail mailable
            Mail::to($user->email)->send(new WelcomeMail($user));
            
            Log::info('Welcome email sent successfully to: ' . $user->email);
            
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email: ' . $e->getMessage());
            throw $e;
        }
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

            // Send OTP email using the OTPMail mailable
            Mail::to($email)->send(new OTPMail($userInfo, $otp, $appName));

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
