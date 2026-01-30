<?php

namespace App\Services\Profile;

use App\Models\PasskeyBackup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class PasskeyService
{
    public function backupPassKey(array $data)
    {
        $userInfo = json_decode(Session::get('authenticatedUserInfo'), true);
        $username = $userInfo['username'];

        if ($username != $userInfo['username']) {
            return response()->json([
                'success' => false,
                'message' => 'Username comparision failed!',
            ]);
        }

        PasskeyBackup::updateOrCreate(
            ['username' => $username],
            [
                'ciphertext' => $data['cipherText'],
                'iv' => $data['iv'],
                'tag' => $data['tag'],
            ]
        );

        Log::info('Passkey backup saved/updated', [
            'username' => $username,
        ]);
    }

    public function retrievePasskeyBackup(): array
    {

        $user = Auth::user();
        $backup = PasskeyBackup::where('username', $user->username)
            ->orderBy('updated_at', 'desc')
            ->firstOrFail();

        return [
            'ciphertext' => $backup->ciphertext,
            'iv' => $backup->iv,
            'tag' => $backup->tag,
        ];
    }
}
