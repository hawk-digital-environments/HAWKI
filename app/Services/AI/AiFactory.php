<?php
declare(strict_types=1);


namespace App\Services\AI;


use App\Services\AI\AvailModels\AvailableModelsBuilder;
use App\Services\AI\AvailableModels\AvailableModelsBuilderBuilder;
use App\Services\AI\Config\AiConfigService;
use App\Services\AI\Db\ModelStatusDb;
use App\Services\AI\Exception\InvalidClientClassException;
use App\Services\AI\Exception\MissingRequiredAiServiceClassException;
use App\Services\AI\Interfaces\ClientInterface;
use App\Services\AI\Interfaces\ModelProviderInterface;
use App\Services\AI\Providers\GenericModelProvider;
use App\Services\AI\Utils\ModelAwareClient;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiModelContext;
use App\Services\AI\Value\AvailableAiModels;
use App\Services\AI\Value\ModelUsageType;
use App\Services\AI\Value\ProviderConfig;
use Illuminate\Config\Repository;
use Illuminate\Container\Attributes\Singleton;
use Psr\Container\ContainerInterface;

#[Singleton]
class AiFactory
{
    private array $instances;
    
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Repository         $config,
        private readonly AiConfigService    $aiConfigService
    )
    {
    }
    
    public function getAvailableModels(ModelUsageType $usageType): AvailableAiModels
    {


        return $this->rememberInstance('available_models_' . $usageType->value, function () use ($usageType) {
            $builder = $this->rememberInstance(AvailableModelsBuilder::class, function () {
                $builder = $this->container->get(AvailableModelsBuilderBuilder::class);
                
                // Use AiConfigService to get models from either config or database
                $defaultModels = $this->aiConfigService->getDefaultModels(ModelUsageType::DEFAULT);
                $defaultModelsExtApp = $this->aiConfigService->getDefaultModels(ModelUsageType::EXTERNAL_APP);
                $systemModels = $this->aiConfigService->getSystemModels(ModelUsageType::DEFAULT);
                $systemModelsExtApp = $this->aiConfigService->getSystemModels(ModelUsageType::EXTERNAL_APP);
                
                foreach ($defaultModels as $modelType => $modelName) {
                    $builder->addDefaultModelName($modelType, ModelUsageType::DEFAULT, $modelName);
                }
                
                foreach ($defaultModelsExtApp as $modelType => $modelName) {
                    $builder->addDefaultModelName($modelType, ModelUsageType::EXTERNAL_APP, $modelName);
                }
                
                foreach ($systemModels as $modelType => $modelName) {
                    $builder->addSystemModelName($modelType, ModelUsageType::DEFAULT, $modelName);
                }
                
                foreach ($systemModelsExtApp as $modelType => $modelName) {
                    $builder->addSystemModelName($modelType, ModelUsageType::EXTERNAL_APP, $modelName);
                }
                
                $i = $builder->build();
                
                $totalModels = 0;
                foreach ($this->createProviderList() as $provider) {
                    $providerModels = 0;
                    foreach ($provider->getModels() as $model) {
                        $i->addModel(
                            AiModel::bindContext(
                                $model,
                                $this->createModelContext($provider, $model)
                            )
                        );
                        $providerModels++;
                        $totalModels++;
                    }
                }
                
                return $i;
            });
            
            return $builder->build($usageType);
        });
    }
    
    private function createProviderList(): iterable
    {
        $providers = $this->aiConfigService->getProviders();


        foreach ($providers as $providerId => $rawConfig) {
            

            $config = new ProviderConfig($providerId, $rawConfig);
            if (!$config->isActive()) {

                continue;
            }
            
            /** @var class-string<ModelProviderInterface> $modelProviderClass */
            $modelProviderClass = $this->buildProviderRelativeClassName($config, 'ModelProvider');
            if (!class_exists($modelProviderClass)) {
                $modelProviderClass = GenericModelProvider::class;
            }
            
            
            
            yield new $modelProviderClass($config);
        }
    }
    
    private function createModelContext(ModelProviderInterface $provider, AiModel $model): AiModelContext
    {
        $status = null;
        
        return new AiModelContext(
            $model,
            $provider,
            function (AiModel $model) use ($provider) {
                return $this->rememberInstance(
                    'client_for_' . $provider->getConfig()->getId() . '_model_' . $model->getId(),
                    function () use ($provider, $model) {
                        return new ModelAwareClient(
                            $this->getClientForProvider($provider),
                            $model
                        );
                    }
                );
            },
            function (AiModel $model) use (&$status) {
                if ($status === null) {
                    $status = $this->container->get(ModelStatusDb::class)->getStatus($model);
                }
                return $status;
            }
        );
    }
    
    private function getClientForProvider(ModelProviderInterface $provider): ClientInterface
    {
        return $this->rememberInstance(
            'client_for_provider_' . $provider->getConfig()->getId(),
            function () use ($provider) {
                $clientClass = $this->buildProviderRelativeClassName($provider->getConfig(), 'Client');
                
                if (!class_exists($clientClass)) {
                    throw new MissingRequiredAiServiceClassException($clientClass);
                }
                
                $client = $this->container->get($clientClass);
                if (!$client instanceof ClientInterface) {
                    throw new InvalidClientClassException($clientClass);
                }
                
                $client->setProvider($provider);
                
                return $client;
            }
        );
    }
    
    private function buildProviderRelativeClassName(ProviderConfig $config, string $className): string
    {
        $adapter = ucfirst($config->getAdapterName());
        return __NAMESPACE__ . '\\Providers\\' . $adapter . '\\' . $adapter . ucfirst($className);
    }
    
    private function rememberInstance(string $key, callable $callback): object
    {
        if (!isset($this->instances[$key])) {
            $this->instances[$key] = $callback();
        }
        
        return $this->instances[$key];
    }
}
