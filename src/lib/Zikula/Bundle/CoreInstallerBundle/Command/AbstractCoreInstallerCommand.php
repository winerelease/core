<?php

declare(strict_types=1);

/*
 * This file is part of the Zikula package.
 *
 * Copyright Zikula Foundation - https://ziku.la/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zikula\Bundle\CoreInstallerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zikula\Bundle\CoreBundle\HttpKernel\ZikulaKernel;
use Zikula\Common\Translator\TranslatorInterface;

abstract class AbstractCoreInstallerCommand extends Command
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var array
     * @see \Zikula\Bundle\CoreInstallerBundle\Command\Install\StartCommand
     */
    protected $settings = [
        /* Database */
        'database_host' => [
            'description' => 'The location of your database, most of the times "localhost".',
            'default' => 'localhost'
        ],
        'database_user' => [
            'description' => 'The database user.',
            'default' => null
        ],
        'database_password' => [
            'description' => 'Your database user\'s password.',
            'default' => null,
        ],
        'database_name' => [
            'description' => 'The name of the database.',
            'default' => null
        ],
        'database_driver' => [
            'description' => 'Your database driver.',
            'default' => 'mysql'
        ],
        'dbtabletype' => [
            'description' => 'The database storage engine.',
            'default' => 'innodb'
        ],
        /* Admin user */
        'username' => [
            'description' => 'Username of the new Zikula admin user.',
            'default' => 'admin'
        ],
        'password' => [
            'description' => 'Password of the new Zikula admin user.',
            'default' => null,
        ],
        'email' => [
            'description' => 'Email of the new Zikula admin user.',
            'default' => null
        ],
        /* Http settings */
        'router:request_context:host' => [
            'description' => 'The root domain where you install Zikula, e.g. "example.com". Do not include subdirectories.',
            'default' => null,
        ],
        'router:request_context:scheme' => [
            'description' => 'The scheme of where you install Zikula, can be either "http" or "https".',
            'default' => 'http',
        ],
        'router:request_context:base_url' => [
            'description' => 'The url path of the directory where you install Zikula, leave empty if you install it at the top level. Example: /my/sub-dir',
            'default' => '',
        ],
        'locale' => [
            'description' => 'The locale to use.',
            'default' => 'en'
        ]
    ];

    public function __construct(TranslatorInterface $translator)
    {
        parent::__construct();
        $this->translator = $translator;
    }

    protected function printWarnings(OutputInterface $output, $warnings): void
    {
        foreach ($warnings as $warning) {
            $output->writeln('<error>' . $warning . '</error>');
        }
    }

    protected function printRequirementsWarnings(OutputInterface $output, $warnings): void
    {
        $failures = [];
        foreach ($warnings as $key => $value) {
            if (true === $value) {
                continue;
            }
            $failures[] = $this->errorCodeToMessage($key);
        }
        $this->printWarnings($output, $failures);
    }

    private function errorCodeToMessage(string $key): string
    {
        $messages = [
            'phpsatisfied' => $this->translator->__f('You have got a problem! Your PHP version is %actual, which does not satisfy the Zikula system requirement of version %required or later.', ['%actual' => PHP_VERSION, '%required' => ZikulaKernel::PHP_MINIMUM_VERSION]),
            'datetimezone' => $this->translator->__('date.timezone is currently not set.  It needs to be set to a valid timezone in your php.ini such as timezone like UTC, GMT+5, Europe/Berlin.'),
            'pdo' => $this->translator->__("Your PHP installation doesn't have the PDO extension loaded."),
            'phptokens' => $this->translator->__("You have got a problem! Your PHP installation does not have the token functions available, but they are necessary for Zikula's output system."),
            'mbstring' => $this->translator->__('Your PHP installation does not have the multi-byte string functions available. Zikula needs this to handle multi-byte character sets.'),
            'pcreUnicodePropertiesEnabled' => $this->translator->__("Your PHP installation's PCRE library does not have Unicode property support enabled. Zikula needs this to handle multi-byte character sets in regular expressions. The PCRE library used with PHP must be compiled with the '--enable-unicode-properties' option."),
            'json_encode' => $this->translator->__('Your PHP installation does not have the JSON functions available. Zikula needs this to handle AJAX requests.'),
            'config_personal_config_php' => $this->translator->__f("'%s' has been found. This is not OK: please rename this file before continuing the installation process.", ['%s' => 'config/personal_config.php'])/*,
            'custom_parameters_yml' => $this->translator->__f("'%s' has been found. This is not OK: please rename this file before continuing the installation process.", "app/config/custom_parameters.yml")*/
        ];
        if (array_key_exists($key, $messages)) {
            return $messages[$key];
        }

        // remaining keys are filenames
        return $this->translator->__f("You have a problem! '%s' is not writeable. Please ensure that the permissions are set correctly for the installation process.", ['%s' => $key]);
    }

    protected function printSettings($givenSettings, SymfonyStyle $io): void
    {
        $rows = [];
        foreach ($givenSettings as $name => $givenSetting) {
            if (isset($this->settings[$name]['password']) && $this->settings[$name]['password']) {
                $givenSetting = str_repeat('*', mb_strlen($givenSetting));
            }
            $rows[] = [$name, $givenSetting];
        }
        $io->table([$this->translator->__('Parameter'), $this->translator->__('Value')], $rows);
    }
}
