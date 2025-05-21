<?php

namespace App\Controller;

use App\Entity\Bloc;
use App\Entity\Exercice;
use App\Entity\Organisation;
use App\Entity\Type;
use App\Entity\User;
use App\Form\BlocType;
use App\Form\ExerciceType;
use App\Form\RegistrationFormType;
use App\Form\ResExerciceType;
use App\Form\TypeType;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MainController extends AbstractController
{
    #[Route('/', name: 'app_main')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $exercices = $entityManager->getRepository(Exercice::class)->findBy([
            'organisation' => $this->getUser()->getOrganisation()
        ]);
        return $this->render('main/index.html.twig', [
            'exercices' => $exercices
        ]);
    }

    #[Route('/profil', name: 'app_profil')]
    public function profil(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        return $this->render('main/profil.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/exercice/new', name: 'app_new_exercice')]
    public function new_exercice(EntityManagerInterface $entityManager): Response
    {
        // order by type
        $types = $entityManager->getRepository(Type::class)->findAll();
        $blocs = [];
        foreach ($types as $type) {
            $blocs[$type->getNom()] = $type->getBlocs();
        }

        return $this->render('main/new_exercice.html.twig', [
            'blocs' => $blocs
        ]);
    }

    #[Route('/exercice/save', name: 'app_save_exercice', methods: ['POST'])]
    public function save_exercice(Request $request, EntityManagerInterface $entityManager): Response
    {
        $data = $request->request->all();

        $name = $data["nom"];
        $description = $data["description"];
        $jsonData = json_decode($data["json"], true); // Ajoutez true pour obtenir un array

        $exercice = new Exercice();
        $exercice->setInput($jsonData['input']);
        $exercice->setOutput($jsonData['solutions']);
        $exercice->setTypeOrder($jsonData['typeOrder']);
        $exercice->setNom($name);
        $exercice->setDescription($description);
        $exercice->setOrganisation($this->getUser()->getOrganisation());

        $entityManager->persist($exercice);
        $entityManager->flush();

        $this->addFlash('success', 'Exercice créé avec succès !');
        return $this->redirectToRoute('app_main');
    }

    #[Route('/exercice/list', name: 'app_exercice_list')]
    public function exercice_list(EntityManagerInterface $entityManager): Response
    {
        if(!$this->getUser()) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }
        $user = $this->getUser();
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $exercices = $entityManager->getRepository(Exercice::class)->findAll();
        } else {
            $exercices = $entityManager->getRepository(Exercice::class)->findBy([
                'organisation' => $this->getUser()->getOrganisation()
            ]);
        }

        return $this->render('main/exercice_list.html.twig', [
            'exercices' => $exercices
        ]);
    }

    #[Route('/exercice/edit/{id}', name: 'app_exercice_edit')]
    public function exercice_edit($id, EntityManagerInterface $entityManager): Response
    {
        $exercice = $entityManager->getRepository(Exercice::class)->find($id);

        if (!$exercice) {
            throw $this->createNotFoundException("Exercice non trouvé !");
        }

        // order by type
        $types = $entityManager->getRepository(Type::class)->findAll();
        $blocs = [];
        foreach ($types as $type) {
            $blocs[$type->getNom()] = $type->getBlocs();
        }

        return $this->render('main/exercice_edit.html.twig', [
            'exercice' => $exercice,
            'blocs' => $blocs
        ]);
    }

    #[Route('/bloc/new', name: 'app_new_bloc')]
    public function new_bloc(Request $request, EntityManagerInterface $entityManager): Response
    {
        $bloc = new Bloc();

        $form = $this->createForm(BlocType::class, $bloc);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($bloc);
            $entityManager->flush();
            return $this->redirectToRoute('app_main');
        }

        return $this->render('main/new_bloc.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/bloc/list', name: 'app_list_bloc')]
    public function list_bloc(EntityManagerInterface $entityManager): Response
    {
        $blocs = $entityManager->getRepository(Bloc::class)->findAll();

        return $this->render('main/bloc_list.html.twig', [
            'blocs' => $blocs
        ]);
    }

    #[Route('/type/new', name: 'app_new_type')]
    public function new_type(Request $request, EntityManagerInterface $entityManager): Response
    {
        $type = new Type();

        $form = $this->createForm(TypeType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($type);
            $entityManager->flush();
            return $this->redirectToRoute('app_main');
        }

        return $this->render('main/new_type.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/type/edit/{id}', name: 'app_edit_type')]
    public function edit_type($id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $type = $entityManager->getRepository(Type::class)->find($id);

        if (!$type) {
            throw $this->createNotFoundException("Type non trouvé !");
        }

        $form = $this->createForm(TypeType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($type);
            $entityManager->flush();
            return $this->redirectToRoute('app_main');
        }

        return $this->render('main/edit_type.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/type/list', name: 'app_list_type')]
    public function list_type(EntityManagerInterface $entityManager): Response
    {
        $types = $entityManager->getRepository(Type::class)->findAll();

        return $this->render('main/type_list.html.twig', [
            'types' => $types
        ]);
    }

    #[Route('/exercice/{id}', name: 'app_exercice')]
    public function exercice($id, EntityManagerInterface $entityManager, Request $request): Response
    {
        $exercice = $entityManager->getRepository(Exercice::class)->find($id);

        if (!$exercice) {
            throw $this->createNotFoundException("Exercice non trouvé !");
        }

        // Vérification de l'organisation de l'utilisateur (sauf si admin)
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            $user = $this->getUser();
            if (!$user || $user->getOrganisation() !== $exercice->getOrganisation()) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cet exercice.');
            }
        }

        $inputData = $exercice->getInput();

        $blocIds = [];
        foreach ($inputData as $typeId => $blocs) {
            foreach ($blocs as $valeur) {
                $blocIds[] = $valeur;
            }
        }

        $blocs = $entityManager->getRepository(Bloc::class)->createQueryBuilder('b')
            ->where('b.id IN (:blocIds)')
            ->setParameter('blocIds', $blocIds)
            ->getQuery()
            ->getResult();

        $blocsByType = [];
        foreach ($blocs as $bloc) {
            $typeId = $bloc->getType()->getId();
            $blocsByType[$typeId][$bloc->getId()] = $bloc->getValeur();
        }

        // Gestion de la requête AJAX
        if ($request->isXmlHttpRequest()) {
            $data = json_decode($request->getContent(), true);
            $userInput = $data['response']; // exemple: [5, 2, 4]

            $outputList = $exercice->getOutput(); // exemple: ["1" => ["1" => 1, "2" => 3, "3" => 4], ...]
            $orderedOutput = $exercice->getTypeOrder(); // exemple: [2, 1, 3]

            foreach ($outputList as $output) {
                $expected = [];
                foreach ($orderedOutput as $typeId) {
                    $typeId = (string)$typeId;
                    $expected[] = $output[$typeId];
                }

                if ($userInput === $expected) {
                    return $this->json([
                        'success' => true,
                        'message' => 'Bravo !',
                    ]);
                }
            }

            return $this->json([
                'success' => false,
                'message' => 'Réponse incorrecte.',
            ]);
        }

        return $this->render('main/exercice.html.twig', [
            'exercice' => $exercice,
            'blocs' => $blocs,
        ]);
    }
}
