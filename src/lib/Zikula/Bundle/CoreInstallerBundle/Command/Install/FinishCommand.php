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

namespace Zikula\Bundle\CoreInstallerBundle\Command\Install;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zikula\Bundle\CoreInstallerBundle\Command\AbstractCoreInstallerCommand;
use Zikula\Bundle\CoreInstallerBundle\Manager\StageManager;
use Zikula\Bundle\CoreInstallerBundle\Stage\Install\AjaxInstallerStage;
use Zikula\Common\Translator\TranslatorInterface;

class FinishCommand extends AbstractCoreInstallerCommand
{
    /**
     * @var string
     */
    private $environment;

    /**
     * @var string
     */
    private $installed;

    /**
     * @var StageManager
     */
    private $stageManager;

    public function __construct(
        string $environment,
        bool $installed,
        StageManager $stageManager,
        TranslatorInterface $translator
    ) {
        parent::__construct($translator);
        $this->installed = $installed;
        $this->environment = $environment;
        $this->stageManager = $stageManager;
    }

    protected function configure()
    {
        $this
            ->setName('zikula:install:finish')
            ->setDescription('Call this command after zikula:install:start')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (true === $this->installed) {
            $io->error($this->translator->__('Zikula already appears to be installed.'));

            return;
        }

        $io->section($this->translator->__('*** INSTALLING ***'));
        $io->comment($this->translator->__f('Configuring Zikula installation in %env% environment.', ['%env%' => $this->environment]));

        // install!
        $ajaxInstallerStage = new AjaxInstallerStage($this->translator);
        $stages = $ajaxInstallerStage->getTemplateParams();
        foreach ($stages['stages'] as $key => $stage) {
            $io->text($stage[AjaxInstallerStage::PRE]);
            $io->text('<fg=blue;options=bold>' . $stage[AjaxInstallerStage::DURING] . '</fg=blue;options=bold>');
            $status = $this->stageManager->executeStage($stage[AjaxInstallerStage::NAME]);
            if ($status) {
                $io->success($stage[AjaxInstallerStage::SUCCESS]);
            } else {
                $io->error($stage[AjaxInstallerStage::FAIL]);
            }
        }
        $io->success($this->translator->__('INSTALL COMPLETE!'));
    }
}
