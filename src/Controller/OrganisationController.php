<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Form\EndUserFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class OrganisationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route('/user/list', name: 'app_user_list')]
    public function user_list(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $users = $entityManager->getRepository(User::class)->findAll();
        } else {
            $organisation = $user->getOrganisation();
            if (!$organisation) {
                throw $this->createAccessDeniedException('Vous n\'avez pas d\'organisation associée.');
            }

            $users = $entityManager->getRepository(User::class)->findBy(['organisation' => $organisation]);
        }

        return $this->render('organisation/user_list.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/user/add', name: 'app_add_user')]
    public function add_user(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher, MailerInterface $mailer): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        $organisation = $user->getOrganisation();
        if (!$organisation) {
            throw $this->createAccessDeniedException('Vous n\'avez pas d\'organisation associée.');
        }

        if(!in_array('ROLE_PROFESSEUR', $user->getRoles())) {
            return $this->render('organisation/add_user.html.twig', [
                'can_add_user' => false,
            ]);
        }

        // Logique pour ajouter un utilisateur à l'organisation
        $nb_licences = $organisation->getLicences();
        // get nb users under licence with the ROLE_USER
        $conn = $entityManager->getConnection();
        $sql = '
            SELECT COUNT(*) FROM user
            WHERE organisation_id = :orgId
            AND JSON_CONTAINS(roles, :role)
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'orgId' => $organisation->getId(),
            'role' => json_encode('ROLE_USER')
        ]);

        $nb_users = $result->fetchOne();

        if ($nb_users >= $nb_licences) {
            return $this->render('organisation/add_user.html.twig', [
                'can_add_user' => true,
                'has_licence' => false,
            ]);
        }

        // Logique pour ajouter un utilisateur à l'organisation
        $new_user = new User();

        $form = $this->createForm(EndUserFormType::class, $new_user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $new_user->setOrganisation($organisation);
            $new_user->setRoles(['ROLE_USER']);
            // generate temporary password
            $password = bin2hex(random_bytes(10));
            $new_user->setPassword(
                $userPasswordHasher->hashPassword(
                    $new_user,
                    $password
                )
            );

            $new_user->setIsTempPassword(true);
            $new_user->setIsVerified(true);

            $entityManager->persist($new_user);
            $entityManager->flush();

            // send email to the new user with the password
            $email = (new TemplatedEmail())
                ->from(new Address('oneclick.web59@gmail.com', 'La Méthode Decroix'))
                ->to((string) $new_user->getEmail())
                ->subject('Votre compte a été créé')
                ->htmlTemplate('organisation/creation_compte_email.html.twig')
                ->context([
                    'user' => $new_user,
                    'password' => $password,
                ]);

            $mailer->send($email);


            return $this->redirectToRoute('app_user_list');
        }

        return $this->render('organisation/add_user.html.twig', [
            'can_add_user' => true,
            'has_licence' => true,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/user/add_licences', name: 'app_add_licences')]
    public function add_licences(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        $organisation = $user->getOrganisation();
        if (!$organisation) {
            throw $this->createAccessDeniedException('Vous n\'avez pas d\'organisation associée.');
        }

        if(!in_array('ROLE_PROFESSEUR', $user->getRoles())) {
            return $this->render('organisation/add_licences.html.twig', [
                'can_add_licence' => false,
            ]);
        }

        return $this->render('organisation/add_licences.html.twig');
    }

    #[Route('/acheter-licences', name: 'app_acheter_licences', methods: ['POST'])]
    public function acheterLicences(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }
        $user = $entityManager->getRepository(User::class)->find($user->getId());

        $organisation = $user->getOrganisation();
        if (!$organisation) {
            throw $this->createAccessDeniedException('Vous n\'avez pas d\'organisation associée.');
        }

        if(!in_array('ROLE_PROFESSEUR', $user->getRoles())) {
            return $this->render('organisation/add_licences.html.twig', [
                'can_add_licence' => false,
            ]);
        }

        $nbLicences = (int) $request->request->get('nb_licences');
        if ($nbLicences <= 0) {
            $this->addFlash('error', 'Le nombre de licences doit être supérieur à 0.');
            return $this->redirectToRoute('app_acheter_licences');
        }

        $product = $entityManager->getRepository(Product::class)->findOneBy(['nom' => 'Licence Utilisateur']);

        $order = new Order();
        $order->setUser($user);
        $order->setQuantity($nbLicences);
        $order->setProduct($product);
        $order->setIsPaid(false);
        $order->setDateInit(new \DateTime());
        $entityManager->persist($order);
        $entityManager->flush();

        // Rediriger vers la page de paiement Stripe
        return $this->redirectToRoute('app_payment', ['orderId' => $order->getId()]);
    }

    #[Route('/orders', name: 'app_orders')]
    public function orders(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        $orders = $entityManager->getRepository(Order::class)->findBy(['user' => $user]);

        return $this->render('organisation/orders.html.twig', [
            'orders' => $orders,
        ]);
    }
}
