<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-email',
    description: 'Test the email configuration',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Testing email configuration...');

        // Afficher la configuration DSN (masquée)
        $dsn = $_ENV['MAILER_DSN'] ?? 'Not set';
        $maskedDsn = preg_replace('/:[^@]+@/', ':****@', $dsn);
        $output->writeln('MAILER_DSN: ' . $maskedDsn);

        $email = (new Email())
            ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'test@alo-service.com')
            ->to('alorodo@gmail.com') // Remplacez par votre vrai email
            ->subject('Test email from Alo Service Web')
            ->text('Ceci est un email de test.')
            ->html('<p>Ceci est un email de test.</p>');

        try {
            $this->mailer->send($email);
            $output->writeln('<info>✓ Email envoyé avec succès!</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>✗ Erreur lors de l\'envoi:</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
