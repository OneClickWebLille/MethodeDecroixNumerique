<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class StripeController extends AbstractController
{
    #[Route('/payment/id/{orderId}', name: 'app_payment')]
    public function payment($orderId, EntityManagerInterface $entityManager): Response
    {
        $order = $entityManager->getRepository(Order::class)->find($orderId);

        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        Stripe::setApiKey($this->getParameter('stripe_secret_key'));

        $unitPrice = ($order->getProduct()->getPrix()) * 100; // 30€ par licence, par exemple
        $quantity = $order->getQuantity(); // ou autre propriété pertinente

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $quantity . ' licence(s) Utilisateur(s)',
                    ],
                    'unit_amount' => $unitPrice,
                ],
                'quantity' => $quantity,
            ]],
            'mode' => 'payment',
            'metadata' => [
                'order_id' => $order->getId(),
            ],
            'success_url' => $this->generateUrl('app_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'cancel_url' => $this->generateUrl('app_payment_cancel', [
                'orderId' => $order->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return $this->redirect($session->url);
    }

    #[Route('/payment/success', name: 'app_payment_success')]
    public function paymentSuccess(): Response
    {
        return $this->render('payment/success.html.twig');
    }

    #[Route('/payment/cancel/{orderId}', name: 'app_payment_cancel')]
    public function paymentCancel(Request $request, $orderId): Response
    {
        return $this->render('payment/cancel.html.twig');
    }

    #[Route('/stripe/webhook', name: 'stripe_webhook')]
    public function stripeWebhook(Request $request, EntityManagerInterface $entityManager): Response
    {
        $payload = $request->getContent();
        $sig_header = $request->headers->get('stripe-signature');
        $endpoint_secret = $this->getParameter('stripe_webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch(\UnexpectedValueException $e) {
            return new Response('Invalid payload', 400);
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            return new Response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            $orderId = $session->metadata->order_id ?? null;
            if ($orderId) {
                $order = $entityManager->getRepository(Order::class)->find($orderId);
                if ($order && !$order->isPaid()) {
                    $order->setIsPaid(true);
                    $order->setDatePayment(new \DateTime());

                    $organisation = $order->getUser()->getOrganisation();
                    $organisation->setLicences($organisation->getLicences() + $order->getQuantity());
                    $entityManager->persist($organisation);
                    $entityManager->persist($order);
                    $entityManager->flush();
                }
            }
        }

        return new Response('Webhook reçu', 200);
    }
}
