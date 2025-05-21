<?php
namespace App\Controller;

use App\Form\ContactType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contactData = $form->getData();

            // Validation des données du formulaire
            if (empty($contactData['name']) || empty($contactData['email']) || empty($contactData['message'])) {
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
                return $this->redirectToRoute('app_contact');
            }
            if (!filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'L\'adresse email est invalide.');
                return $this->redirectToRoute('app_contact');
            }
            if (strlen($contactData['message']) < 10) {
                $this->addFlash('error', 'Le message doit contenir au moins 10 caractères.');
                return $this->redirectToRoute('app_contact');
            }

            // Envoi de l'email
            $email = (new Email())
                ->from(new Address('oneclick.web59@gmail.com', 'La Méthode Decroix'))
                ->to('oneclick.web59@gmail.com')
                ->subject('Nouveau message de contact')
                ->text($contactData['message'])
                ->html($this->renderView('emails/contact.html.twig', [
                    'name' => $contactData['name'],
                    'email' => $contactData['email'],
                    'message' => $contactData['message']
                ])
                );
            $mailer->send($email);

            $this->addFlash('success', 'Votre message a bien été envoyé ! Nous vous répondrons dès que possible.');

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('main/contact.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
