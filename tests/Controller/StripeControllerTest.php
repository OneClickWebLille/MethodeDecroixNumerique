<?php
namespace App\Tests\Controller;

use App\Entity\Order;
use App\Entity\Organisation;
use App\Entity\Product;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class StripeControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Clean DB before each test
        foreach ([
            Order::class,
            Product::class,
            User::class,
            Organisation::class,
        ] as $entityClass) {
            $items = $this->entityManager->getRepository($entityClass)->findAll();
            foreach ($items as $item) {
                $this->entityManager->remove($item);
            }
        }
        $this->entityManager->flush();
    }

    public function testPaymentRedirectToStripe(): void
    {
        // Setup Organisation
        $organisation = new Organisation();
        $organisation->setNom('Test Org');
        $organisation->setLicences(0); // assuming you have this field

        // Setup User
        $user = new User();
        $user->setNom('Doe');
        $user->setPrenom('John');
        $user->setEmail('john@example.com');
        $user->setRoles(['ROLE_PROFESSEUR']);
        $user->setOrganisation($organisation);
        $user->setIsVerified(true);
        $user->setIsTempPassword(false);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));

        // Setup Product
        $product = new Product();
        $product->setNom('Licence 1 utilisateur');
        $product->setPrix(30); // 30 € licence

        // Setup Order
        $order = new Order();
        $order->setUser($user);
        $order->setProduct($product);
        $order->setQuantity(2);
        $order->setIsPaid(false);
        $order->setDateInit(new \DateTime());

        // Persist everything
        $this->entityManager->persist($organisation);
        $this->entityManager->persist($user);
        $this->entityManager->persist($product);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // login
        $this->client->loginUser($user);

        // Simulate payment page access
        $this->client->request('GET', '/payment/id/' . $order->getId());

        // Assert redirection to Stripe
        $this->assertResponseRedirects(null, 302);
        $redirectUrl = $this->client->getResponse()->headers->get('Location');

        $this->assertStringStartsWith('https://checkout.stripe.com', $redirectUrl);
    }
}
