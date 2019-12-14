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

namespace Zikula\Bundle\CoreInstallerBundle\Stage\Install;

use Swift_Mailer;
use Swift_Message;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Zikula\Common\Translator\Translator;
use Zikula\Common\Translator\TranslatorInterface;
use Zikula\Common\Translator\TranslatorTrait;
use Zikula\Component\Wizard\InjectContainerInterface;
use Zikula\Component\Wizard\StageInterface;
use Zikula\Component\Wizard\WizardCompleteInterface;
use Zikula\UsersModule\Constant as UserConstant;
use Zikula\UsersModule\Entity\Repository\UserRepository;

class CompleteStage implements StageInterface, WizardCompleteInterface, InjectContainerInterface
{
    use TranslatorTrait;

    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->setTranslator($container->get(Translator::class));
    }

    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function getName(): string
    {
        return 'complete';
    }

    public function getTemplateName(): string
    {
        return '';
    }

    public function isNecessary(): bool
    {
        return true;
    }

    public function getTemplateParams(): array
    {
        return [];
    }

    public function getResponse(Request $request): Response
    {
        $router = $this->container->get('router');
        if ($this->sendEmailToAdmin($request) > 0) {
            $request->getSession()->getFlashBag()->add('success', $this->__('Congratulations! Zikula has been successfully installed.'));
            $request->getSession()->getFlashBag()->add('info', $this->__f(
                'Session are currently configured to use the filesystem. It is recommended that you change this to use the database. Click %here% to configure.',
                ['%here%' => '<a href="' . $router->generate('zikulasecuritycentermodule_config_config') . '">' . $this->__('Security Center') . '</a>']
            ));

            return new RedirectResponse($router->generate('zikulaadminmodule_admin_adminpanel', [], RouterInterface::ABSOLUTE_URL));
        }
        $request->getSession()->getFlashBag()->add('warning', $this->__('Email settings are not yet configured. Please configure them below.'));

        return new RedirectResponse($router->generate('zikulamailermodule_config_config', [], RouterInterface::ABSOLUTE_URL));
    }

    private function sendEmailToAdmin(Request $request): int
    {
        $adminUser = $this->container->get(UserRepository::class)->find(UserConstant::USER_ID_ADMIN);
        $uName = $adminUser->getUname();
        $url = $request->getSchemeAndHttpHost() . $request->getBasePath();
        $locale = $request->getLocale();

        $subject = $this->__('Zikula installation completed!');
        $body = <<<EOF
<html lang="${locale}">
<head>
    <title>${subject}</title>
</head>
<body>
<h1>Hi ${uName}!</h1>
<p>Zikula has been successfully installed at <a href="${url}">${url}</a>. If you have further questions,
visit <a href="https://ziku.la">ziku.la</a></p>
</body>
EOF;
        $message = new Swift_Message($subject, $body, 'text/html');
        $message->setFrom($adminUser->getEmail());
        $message->setTo($adminUser->getEmail());

        /**
         * @var $mailer Swift_Mailer
         */
        $mailer = $this->container->get('mailer');
        try {
            $mailer->send($message);
        } catch (\Exception $exception) {
            return 0;
        }

        return 1;
    }
}
