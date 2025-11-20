<?php
declare(strict_types=1);


namespace App\Http\Controllers;


use App\Mail\OTPMail;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

class OtpController extends Controller
{
    public function __construct(
        private readonly Session $session,
        private readonly LoggerInterface $logger
    )
    {
    }

    /**
     * Send OTP to user's email for passkey alternative authentication
     */
    public function sendOTP(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'email' => 'required|email',
            ]);

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not authenticated',
                ], 401);
            }

            // Verify that the email matches the authenticated user
            if ($user->email !== $request->input('email')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Email does not match authenticated user',
                ], 403);
            }

            // Generate 6-digit OTP
            $otp = str_pad(random_int(0, 999999) . '', 6, '0', STR_PAD_LEFT);

            // Store OTP in session with expiration
            $otpTimeout = config('auth.passkey_otp_timeout', 300); // 5 minutes default
            $this->session->put('otp_code', $otp);
            $this->session->put('otp_expires_at', now()->addSeconds($otpTimeout));
            $this->session->put('otp_user_id', $user->id);

            // Send OTP email
            try {
                Mail::to($user->email)->send(new OTPMail($user, $otp));
                $this->logger->info('OTP email sent successfully to user: ' . $user->username);
            } catch (\Exception $mailException) {
                $this->logger->warning('Failed to send OTP email, but continuing with OTP generation', [
                    'user_id' => $user->id,
                    'error' => $mailException->getMessage(),
                ]);
                // Continue execution even if email fails - OTP is still valid
            }

            // Log OTP for development
            if (app()->environment('local')) {
                $this->logger->info('OTP Code generated for user: ' . $user->username . ' - Code: ' . $otp);
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully to your email',
                'debug_otp' => app()->environment('local') ? $otp : null, // Only in local environment
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            $this->logger->error('OTP sending error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id() ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to send OTP. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify OTP code for passkey alternative authentication
     */
    public function verifyOTP(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'otp' => 'required|string|size:6',
            ]);

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not authenticated',
                ], 401);
            }

            $inputOtp = $request->input('otp');
            $sessionOtp = $this->session->get('otp_code');
            $expiresAt = $this->session->get('otp_expires_at');
            $sessionUserId = $this->session->get('otp_user_id');

            // Check if OTP exists in session
            if (!$sessionOtp || !$expiresAt || !$sessionUserId) {
                return response()->json([
                    'success' => false,
                    'error' => 'No OTP found or session expired',
                ], 400);
            }

            // Check if OTP has expired
            if (now()->gt($expiresAt)) {
                // Clear expired OTP from session
                $this->session->forget(['otp_code', 'otp_expires_at', 'otp_user_id']);

                return response()->json([
                    'success' => false,
                    'error' => 'OTP has expired',
                ], 400);
            }

            // Check if user matches
            if ($sessionUserId !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid session',
                ], 403);
            }

            // Verify OTP
            if ($inputOtp !== $sessionOtp) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid OTP code',
                ], 400);
            }

            // OTP is valid - clear from session
            $this->session->forget(['otp_code', 'otp_expires_at', 'otp_user_id']);

            $this->logger->info('OTP verified successfully for user: ' . $user->username);

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            $this->logger->error('OTP verification error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id() ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to verify OTP. Please try again.',
            ], 500);
        }
    }
}
