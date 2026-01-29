@extends('layouts.apps_layout')

@section('scripts')
    <script src="{{asset('hawki-client.umd.cjs')}}"></script>
    <script src="{{ asset('js/encryption.js') }}"></script>
    <x-internal-frontend-connection/>
@endsection

@section('content')
    <script>
        console.log('hallo');

        (async function () {
            let trys = 0;
            const client = await HawkiClient.createHawkiClient({
                type: 'internal',
                logger: HawkiClient.createDebugLogger(),
                providePasskey: async (
                    validatePasskey,
                    validateBackupHash,
                    backupHashToPasskey,
                    userInfo,
                    salts
                ) => {
                    if (trys++ === 0) {
                        try {
                            const keyData = localStorage.getItem(`${userInfo.username}PK`);
                            const keyJson = JSON.parse(keyData);
                            const key = await deriveKey(userInfo.email, userInfo.username, salts.passkey);
                            const passKey = await decryptWithSymKey(key, keyJson.ciphertext, keyJson.iv, keyJson.tag, false);
                            if (await validatePasskey(passKey)) {
                                return passKey;
                            }
                        } catch (e) {
                            console.error(e);
                        }
                    }

                    for (let i = 0; i < 2; i++) {
                        const passkey = prompt('Enter your passkey');
                        if (!(await validatePasskey(passkey))) {
                            alert('Invalid passkey');
                        } else {
                            return passkey;
                        }
                    }

                    if (confirm('Too many invalid attempts. Do you want to use your recovery code?')) {
                        for (let i = 0; i < 2; i++) {
                            const recoveryCode = prompt('Enter your recovery code');
                            const code = await backupHashToPasskey(recoveryCode);
                            if (code === null) {
                                alert('Invalid recovery code');
                            } else {
                                return code;
                            }
                        }
                    } else {
                        throw new Error('Too many invalid attempts');
                    }

                    alert('Too many invalid attempts');
                }
            });

            await client.sync.all(true);

            console.log('client', client);
        })();
    </script>
@endsection
