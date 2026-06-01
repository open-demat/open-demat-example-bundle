<?php

namespace OpenDemat\ExampleBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\ExampleBundle\Entity\DemandeAchatInterne;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/example/admin', name: 'open_demat_example_admin_')]
final class ExampleAdminHubController extends AbstractController
{
    #[Route('', name: 'hub', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EXAMPLE_POWERUSER');

        $demandes = $em->getRepository(DemandeAchatInterne::class)->findBy([], ['id' => 'DESC']);
        $stats = [
            'total' => count($demandes),
            'soumise' => 0,
            'a_corriger' => 0,
            'validee' => 0,
            'terminee' => 0,
        ];

        foreach ($demandes as $demande) {
            $statut = $demande->getStatutTraitement() ?: 'brouillon';
            if (array_key_exists($statut, $stats)) {
                $stats[$statut]++;
            }
        }

        return $this->render('@OpenDemat/example-bundle/src/templates/admin/hub.html.twig', [
            'stats' => $stats,
        ]);
    }
}
