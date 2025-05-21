<?php
namespace App\Controller;

use App\Entity\Exercice;
use App\Entity\Order;
use App\Entity\Organisation;
use App\Form\OrganisationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    #[Route('/admin/new_organisation', name: 'app_new_organisation')]
    public function createOrgansiation(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(OrganisationType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $organisation = $form->getData();
            $entityManager->persist($organisation);
            $entityManager->flush();

            // Redirect to a success page or show a success message
            return $this->redirectToRoute('app_list_organisation');
        }
        return $this->render('admin/create_organisation.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/list_organisation', name: 'app_list_organisation')]
    public function listOrganisation(EntityManagerInterface $entityManager): Response
    {
        $organisations = $entityManager->getRepository(Organisation::class)->findAll();

        return $this->render('admin/list_organisation.html.twig', [
            'organisations' => $organisations,
        ]);
    }

    #[Route('/admin/orders', name: 'app_admin_orders')]
    public function orders(EntityManagerInterface $entityManager): Response
    {
        $orders = $entityManager->getRepository(Order::class)->findAll();

        return $this->render('admin/orders.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/admin/exercices', name: 'app_admin_exercices')]
    public function exercices(EntityManagerInterface $entityManager): Response
    {
        $exercices = $entityManager->getRepository(Exercice::class)->findAll();

        return $this->render('admin/exercices.html.twig', [
            'exercices' => $exercices,
        ]);
    }
}
