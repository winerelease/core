<?php

declare(strict_types=1);

/*
 * This file is part of the Zikula package.
 *
 * Copyright Zikula - https://ziku.la/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zikula\Bundle\CoreInstallerBundle\Helper;

use RandomLib\Factory;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;
use Zikula\Bundle\CoreBundle\CacheClearer;
use Zikula\Bundle\CoreBundle\Helper\LocalDotEnvHelper;
use Zikula\Bundle\CoreBundle\HttpKernel\ZikulaHttpKernelInterface;
use Zikula\Bundle\CoreBundle\HttpKernel\ZikulaKernel;
use Zikula\Bundle\CoreBundle\YamlDumper;
use Zikula\Component\Wizard\AbortStageException;
use Zikula\ExtensionsModule\Api\ApiInterface\VariableApiInterface;
use Zikula\ExtensionsModule\Api\VariableApi;

class ParameterHelper
{
    /**
     * @var string
     */
    private $configDir;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var VariableApiInterface
     */
    private $variableApi;

    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ZikulaHttpKernelInterface
     */
    private $kernel;

    private $encodedParameterNames = [
        'password',
        'username',
        'email',
        'transport',
        'mailer_id',
        'mailer_key',
        'host',
        'port',
        'customParameters',
        'enableLogging'
    ];

    /**
     * ParameterHelper constructor.
     */
    public function __construct(
        string $projectDir,
        VariableApiInterface $variableApi,
        CacheClearer $cacheClearer,
        RequestStack $requestStack,
        ZikulaHttpKernelInterface $kernel
    ) {
        $this->configDir = $projectDir . '/config';
        $this->projectDir = $projectDir;
        $this->variableApi = $variableApi;
        $this->cacheClearer = $cacheClearer;
        $this->requestStack = $requestStack;
        $this->kernel = $kernel;
    }

    public function getYamlHelper(bool $initCopy = false): YamlDumper
    {
        $copyFile = $initCopy ? 'services.yaml' : null;

        return new YamlDumper($this->configDir, 'services_custom.yaml', $copyFile);
    }

    public function initializeParameters(array $paramsToMerge = []): bool
    {
        $yamlHelper = $this->getYamlHelper(true);
        $params = array_merge($yamlHelper->getParameters(), $paramsToMerge);
        $yamlHelper->setParameters($params);
        $this->cacheClearer->clear('symfony.config');

        return true;
    }

    /**
     * Load and set new default values from the original services.yaml file into the services_custom.yaml file.
     */
    public function reInitParameters(): bool
    {
        $originalParameters = Yaml::parse(file_get_contents($this->kernel->getProjectDir() . '/config/services.yaml'));
        $yamlHelper = $this->getYamlHelper();
        $yamlHelper->setParameters(array_merge($originalParameters['parameters'], $yamlHelper->getParameters()));
        $this->cacheClearer->clear('symfony.config');

        return true;
    }

    /**
     * @throws IOExceptionInterface If .env.local could not be dumped
     */
    public function finalizeParameters(bool $configureRequestContext = true): bool
    {
        $yamlHelper = $this->getYamlHelper();
        $params = $this->decodeParameters($yamlHelper->getParameters());

        $this->variableApi->getAll(VariableApi::CONFIG); // forces initialization of API
        if (!isset($params['upgrading'])) {
            $this->variableApi->set(VariableApi::CONFIG, 'locale', $params['locale']);
            // Set the System Identifier as a unique string.
            if (!$this->variableApi->get(VariableApi::CONFIG, 'system_identifier')) {
                $this->variableApi->set(VariableApi::CONFIG, 'system_identifier', str_replace('.', '', uniqid((string) (random_int(1000000000, 9999999999)), true)));
            }
            // add admin email as site email
            $this->variableApi->set(VariableApi::CONFIG, 'adminmail', $params['email']);
            $this->setMailerData($params);
        }

        $params = array_diff_key($params, array_flip($this->encodedParameterNames)); // remove all encoded params
        $params['datadir'] = !empty($params['datadir']) ? $params['datadir'] : 'public/uploads';

        if ($configureRequestContext) {
            // Configure the Request Context
            // see http://symfony.com/doc/current/cookbook/console/sending_emails.html#configuring-the-request-context-globally
            $request = $this->requestStack->getMasterRequest();
            $hostFromRequest = isset($request) ? $request->getHost() : null;
            $schemeFromRequest = isset($request) ? $request->getScheme() : 'http';
            $basePathFromRequest = isset($request) ? $request->getBasePath() : null;
            $params['router.request_context.host'] = $params['router.request_context.host'] ?? $hostFromRequest;
            $params['router.request_context.scheme'] = $params['router.request_context.scheme'] ?? $schemeFromRequest;
            $params['router.request_context.base_url'] = $params['router.request_context.base_url'] ?? $basePathFromRequest;
        }
        // store the recent version in a config var for later usage. This enables us to determine the version we are upgrading from
        $this->variableApi->set(VariableApi::CONFIG, 'Version_Num', ZikulaKernel::VERSION);

        if (isset($params['upgrading'])) {
            $params['zikula_asset_manager.combine'] = false;

            // unset start page information to avoid missing module errors
            $this->variableApi->set(VariableApi::CONFIG, 'startController_en', '');

            // on upgrade, if a user doesn't add their custom theme back to the /theme dir, it should be reset to a core theme, if available.
            $defaultTheme = (string) $this->variableApi->getSystemVar('Default_Theme');
            if (!$this->kernel->isBundle($defaultTheme) && $this->kernel->isBundle('ZikulaBootstrapTheme')) {
                $this->variableApi->set(VariableApi::CONFIG, 'Default_Theme', 'ZikulaBootstrapTheme');
            }
        } else {
            $this->writeEnvVars();
        }

        // write parameters into config/services_custom.yaml
        $yamlHelper->setParameters($params);

        // clear the cache
        $this->cacheClearer->clear('symfony.config');

        return true;
    }

    private function writeEnvVars()
    {
        $randomLibFactory = new Factory();
        $generator = $randomLibFactory->getMediumStrengthGenerator();
        $vars = [
            'APP_ENV' => 'prod',
            'APP_DEBUG' => 1,
            'APP_SECRET' => '\'' . $generator->generateString(50) . '\'',
            'ZIKULA_INSTALLED' => '\'' . ZikulaKernel::VERSION . '\''
        ];
        $helper = new LocalDotEnvHelper($this->projectDir);
        $helper->writeLocalEnvVars($vars);
    }

    /**
     * Write params to file as encoded values.
     *
     * @throws AbortStageException
     */
    public function writeEncodedParameters(array $data): void
    {
        $yamlHelper = $this->getYamlHelper();
        foreach ($data as $k => $v) {
            $data[$k] = is_string($v) ? base64_encode($v) : $v; // encode so values are 'safe' for json
        }
        $params = array_merge($yamlHelper->getParameters(), $data);
        try {
            $yamlHelper->setParameters($params);
        } catch (IOException $exception) {
            throw new AbortStageException(sprintf('Cannot write parameters to %s file.', 'services_custom.yaml'));
        }
    }

    /**
     * Remove base64 encoding for parameters.
     */
    public function decodeParameters(array $params = []): array
    {
        foreach ($this->encodedParameterNames as $parameterName) {
            if (!empty($params[$parameterName])) {
                $params[$parameterName] = is_string($params[$parameterName]) ? base64_decode($params[$parameterName]) : $params[$parameterName];
            }
        }

        return $params;
    }

    private function setMailerData(array $params): void
    {
        // params have already been decoded
        $mailerParams = array_intersect_key($params, array_flip($this->encodedParameterNames));
        unset($mailerParams['mailer_key'], $mailerParams['password'], $mailerParams['username'], $mailerParams['email']);
        $this->variableApi->setAll('ZikulaMailerModule', $mailerParams);
    }
}
