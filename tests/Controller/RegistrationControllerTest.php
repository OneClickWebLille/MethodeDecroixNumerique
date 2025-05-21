<?php

namespace App\Tests\Controller;

use App\Entity\Exercice;
use App\Entity\Order;
use App\Entity\Organisation;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->userRepository = $container->get(UserRepository::class);
        $orderRepository = $this->entityManager->getRepository(Order::class);
        $organisationRepository = $this->entityManager->getRepository(Organisation::class);
        $exerciceRepository = $this->entityManager->getRepository(Exercice::class);

        // Clean database before each test
        foreach ($orderRepository->findAll() as $order) {
            $this->entityManager->remove($order);
        }
        $this->entityManager->flush();
        foreach ($this->userRepository->findAll() as $user) {
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();
        foreach ($exerciceRepository->findAll() as $exercice) {
            $this->entityManager->remove($exercice);
        }
        $this->entityManager->flush();
        foreach ($organisationRepository->findAll() as $organisation) {
            $this->entityManager->remove($organisation);
        }
        $this->entityManager->flush();
    }

    public function testRegister(): void
    {
        // Access registration page
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Inscription');

        // Submit registration form
        $this->client->submitForm('S\'inscrire', [
            'registration_form[nom]' => 'Doe',
            'registration_form[prenom]' => 'John',
            'registration_form[email]' => 'me@example.com',
            'registration_form[plainPassword]' => 'password',
            'registration_form[organisation]' => 'organisation',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseRedirects('/');
    }
}
