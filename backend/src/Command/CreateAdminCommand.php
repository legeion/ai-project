<?php
//php bin/console app:create-admin admin@example.com admin123
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Créer un utilisateur admin'
)]
class CreateAdminCommand extends Command
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'admin')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe')
            ->addOption('firstName', null, InputOption::VALUE_OPTIONAL, 'Prénom', 'Admin')
            ->addOption('lastName', null, InputOption::VALUE_OPTIONAL, 'Nom', 'System')
            ->addOption('phone', null, InputOption::VALUE_OPTIONAL, 'Téléphone', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        $existingUser = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($existingUser) {
            $io->error('Un utilisateur avec cet email existe déjà.');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($input->getOption('firstName'));
        $user->setLastName($input->getOption('lastName'));
        $user->setPhone($input->getOption('phone'));
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsActive(true);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $password)
        );

        $this->em->persist($user);
        $this->em->flush();

        $io->success('Admin créé avec succès !');

        return Command::SUCCESS;
    }
}
