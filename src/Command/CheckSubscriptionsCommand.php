<?php
namespace App\Command;

use App\Entity\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;

class CheckSubscriptionsCommand extends Command
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure()
    {
        $this
            ->setName('app:check-subscriptions')
            ->setDescription('Checks for expired subscriptions and deactivates them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Checking for expired subscriptions...');

        $today = new \DateTime();
        $expiredUsers = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.subscriptionEndDate < :today')
            ->andWhere('u.isVerified = true')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();

        foreach ($expiredUsers as $user) {
            $user->setIsVerified(false);
            $output->writeln(sprintf(
                'Deactivated user %s (ID: %d) - subscription ended on %s',
                $user->getEmail(),
                $user->getId(),
                $user->getSubscriptionEndDate()->format('Y-m-d')
            ));
        }

        $this->em->flush();

        $output->writeln(sprintf('Deactivated %d expired subscriptions.', count($expiredUsers)));

        return Command::SUCCESS;
    }
}