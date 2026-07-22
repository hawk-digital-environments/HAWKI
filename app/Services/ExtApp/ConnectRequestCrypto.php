<?php
declare(strict_types=1);


namespace App\Services\ExtApp;

use App\Models\ExtApp;
use App\Services\ExtApp\Config\ExtAppConfig;
use App\Services\ExtApp\Repositories\ExtAppRepository;
use App\Services\Frontend\Connection\Values\ExtAppConnectRequestPayload;
use App\Services\System\Time\CarbonClockInterface;
use Carbon\Carbon;
use Hawk\HawkiCrypto\SymmetricCrypto;
use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
readonly class ConnectRequestCrypto
{
    private const string VALIDATOR_KEY = '__validator';
    private const string VALID_UNTIL_KEY = '__validUntil';

    public function __construct(
        #[Config('app.key')]
        private string               $appSecret,
        private ExtAppConfig         $config,
        private SymmetricCrypto      $crypto,
        private ExtAppRepository     $extAppRepository,
        private CarbonClockInterface $clock
    )
    {
    }

    public function encryptPayload(
        ExtAppConnectRequestPayload $payload,
        ExtApp                      $app
    ): string
    {
        $data = $payload->toStringArray();
        $data[self::VALID_UNTIL_KEY] = $this->clock->now()
            ->addSeconds($this->config->externalAppConnectRequestTimeout)
            ->setTimezone('UTC')
            ->timestamp;
        $data[self::VALIDATOR_KEY] = $this->generateValidator($data, $app);
        return base64_encode((string)$this->crypto->encrypt(json_encode($data), $this->appSecret));
    }

    public function decryptPayload(
        string $encryptedPayload
    ): ExtAppConnectRequestPayload|null
    {
        $cryptoValue = SymmetricCryptoValue::fromString(base64_decode($encryptedPayload));
        $decrypted = $this->crypto->decrypt($cryptoValue, $this->appSecret);
        $data = json_decode($decrypted, true);

        if (!is_array($data) || !isset($data[self::VALIDATOR_KEY], $data[self::VALID_UNTIL_KEY])) {
            return null;
        }

        $validator = $data[self::VALIDATOR_KEY];
        $validUntil = Carbon::parse($data[self::VALID_UNTIL_KEY], 'UTC');
        unset($data[self::VALIDATOR_KEY]);

        // The valid until key must be kept to generate the same validator,
        // but it should not be part of the payload that is hydrated, so we create a copy of the data without it.
        $dataToHydrate = $data;
        unset($dataToHydrate[self::VALID_UNTIL_KEY]);

        if ($validUntil->isPast()) {
            return null;
        }

        $payload = ExtAppConnectRequestPayload::fromStringArray($dataToHydrate);

        $app = $this->extAppRepository->findOne($payload->appId);
        if ($app === null) {
            return null;
        }

        $expectedValidator = $this->generateValidator($data, $app);

        if (!hash_equals($expectedValidator, $validator)) {
            return null;
        }

        return $payload;
    }

    private function generateValidator(array $data, ExtApp $app): string
    {
        ksort($data);
        $hash = md5(json_encode($data));
        return hash_hmac('sha256', $hash, $this->appSecret . '-' . $app->app_public_key);
    }
}
