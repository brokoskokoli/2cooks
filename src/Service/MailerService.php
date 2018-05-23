<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Service d'envoi des mails
 */
class MailerService
{
    /**
     * @var \Swift_Mailer
     */
    protected $mailer;

    /**
     * @var EngineInterface|Environment
     */
    protected $templating;

    /**
     * @var \Symfony\Component\Translation\TranslatorInterface
     */
    protected $translator;

    /**
     * MailerService constructor.
     *
     * @param \Swift_Mailer $mailer
     *
     * @param Environment $templating
     * @param \Symfony\Component\Translation\TranslatorInterface $translator
     * @param array $params
     */
    public function __construct(\Swift_Mailer $mailer, Environment $templating, TranslatorInterface $translator)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->translator = $translator;
    }

    /**
     * Send emails
     *
     * @param array $options
     * @param string $template
     * @param array $params
     */
    protected function send($options, $template, $params = array())
    {
        $content = $this->templating->render($template, $params);


        $message = (new \Swift_Message($this->translator->trans('emails.subjectprefix') . $options['subject']))
            ->setFrom('noreply@recipes.stefanrichter.de')
            ->setTo($options['to'])
            ->setBody($content, 'text/html')
            /*
             * If you also want to include a plaintext version of the message
            ->addPart(
                $this->renderView(
                    'emails/registration.txt.twig',
                    array('name' => $name)
                ),
                'text/plain'
            )
            */
        ;

        if (isset($options['cc'])) {
            $message->setCc($options['cc']);
        }

        if (isset($options['bcc'])) {
            $message->setBcc($options['bcc']);
        }

        $this->mailer->send($message);
    }

    public function sendUserResetPasswordEmail(User $user)
    {
        $options = [
            'to' => $user->getEmail(),
            'subject' => $this->translator->trans('emails.forgot_password.subject'),
        ];

        $this->send($options, "mails/forgot_password.html.twig", ['user' => $user]);
    }
}