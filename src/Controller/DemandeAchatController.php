<?php

namespace OpenDemat\ExampleBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Service\AttachmentService;
use OpenDemat\Core\Service\StaticDocumentService;
use OpenDemat\ExampleBundle\Entity\DemandeAchatInterne;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\Registry;

#[Route('/example/demandes-achat')]
final class DemandeAchatController extends AbstractController
{
    private const PROCESS_NAME = 'EXAMPLE';
    private const CASE_TYPE = 'EXAMPLE_demande_achat';

    #[Route('', name: 'open_demat_example_demande_achat_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(EntityManagerInterface $em, Security $security): Response
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $repository = $em->getRepository(DemandeAchatInterne::class);
        $isGlobalList = $this->isGranted('ROLE_EXAMPLE_GESTIONNAIRE');
        $demandes = $isGlobalList
            ? $repository->findBy([], ['id' => 'DESC'])
            : $repository->findBy(['auteur' => $user], ['id' => 'DESC']);

        $stats = [
            'total' => count($demandes),
            'brouillon' => 0,
            'soumise' => 0,
            'a_corriger' => 0,
            'validee' => 0,
            'refusee' => 0,
            'annulee' => 0,
            'terminee' => 0,
        ];

        foreach ($demandes as $demande) {
            $statut = $demande->getStatutTraitement() ?: 'brouillon';
            if (array_key_exists($statut, $stats)) {
                $stats[$statut]++;
            }
        }

        return $this->render('@OpenDemat/example-bundle/src/templates/index.html.twig', [
            'demandes' => $demandes,
            'stats' => $stats,
            'is_global_list' => $isGlobalList,
        ]);
    }

    #[Route('/nouvelle', name: 'open_demat_example_demande_achat_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        Security $security,
        AttachmentService $attachments,
        StaticDocumentService $staticDocuments,
        Registry $workflows,
    ): Response {
        $demande = new DemandeAchatInterne();
        $form = $this->createDemandeForm($demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $securityUser = $security->getUser();

            if (!$securityUser instanceof User || null === $securityUser->getId()) {
                throw new \RuntimeException('Utilisateur non authentifie.');
            }

            $demande->setAuteur($em->getReference(User::class, $securityUser->getId()));

            $em->persist($demande);
            $em->flush();

            $pieceJointe = $form->get('pieceJointe')->getData();
            if ($pieceJointe !== null) {
                $attachments->uploadForCase(
                    processName: self::PROCESS_NAME,
                    caseType: self::CASE_TYPE,
                    caseId: (int) $demande->getId(),
                    files: [$pieceJointe],
                    uploadedBy: $demande->getAuteur(),
                    maxFiles: 10,
                );
            }

            $workflow = $workflows->get($demande, 'example_demande_achat');
            if ($workflow->can($demande, 'soumettre')) {
                $workflow->apply($demande, 'soumettre');
                $em->flush();
            }

            $this->addFlash('success', 'Votre demande a bien ete enregistree et soumise.');

            return $this->redirectToRoute('open_demat_example_demande_achat_index');
        }

        return $this->render('@OpenDemat/example-bundle/src/templates/new.html.twig', [
            'form' => $form->createView(),
            'documentation' => $staticDocuments->findActiveByCode(ExampleAdminStaticDocumentController::DOCUMENTATION_CODE),
        ]);
    }

    #[Route('/{id}', name: 'open_demat_example_demande_achat_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(DemandeAchatInterne $demande, Security $security, AttachmentService $attachments): Response
    {
        $user = $security->getUser();
        $isAuteur = $user instanceof User
            && $demande->getAuteur() instanceof User
            && $demande->getAuteur()->getId() === $user->getId();

        if (!$isAuteur && !$this->isGranted('ROLE_EXAMPLE_GESTIONNAIRE')) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('@OpenDemat/example-bundle/src/templates/view.html.twig', [
            'entity' => $demande,
            'is_auteur' => $isAuteur,
            'correctable_fields' => $this->getCorrectableFields(),
            'attachments' => $attachments->listForCase(
                processName: self::PROCESS_NAME,
                caseType: self::CASE_TYPE,
                caseId: (int) $demande->getId(),
            ),
        ]);
    }

    #[Route('/{id}/soumettre', name: 'open_demat_example_demande_achat_soumettre', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function soumettre(DemandeAchatInterne $demande, Request $request, Registry $workflows, EntityManagerInterface $em): RedirectResponse
    {
        return $this->applyTransition($demande, 'soumettre', $request, $workflows, $em);
    }

    #[Route('/{id}/demander-correction', name: 'open_demat_example_demande_achat_demander_correction', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_EXAMPLE_GESTIONNAIRE')]
    public function demanderCorrection(DemandeAchatInterne $demande, Request $request, Registry $workflows, EntityManagerInterface $em): RedirectResponse
    {
        $demande
            ->setMotifCorrection((string) $request->request->get('motif_correction', ''))
            ->setChampsACorriger($request->request->all('champs_a_corriger'));

        return $this->applyTransition($demande, 'demander_correction', $request, $workflows, $em);
    }

    #[Route('/{id}/valider', name: 'open_demat_example_demande_achat_valider', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_EXAMPLE_GESTIONNAIRE')]
    public function valider(DemandeAchatInterne $demande, Request $request, Registry $workflows, EntityManagerInterface $em): RedirectResponse
    {
        return $this->applyTransition($demande, 'valider', $request, $workflows, $em);
    }

    #[Route('/{id}/refuser', name: 'open_demat_example_demande_achat_refuser', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_EXAMPLE_GESTIONNAIRE')]
    public function refuser(DemandeAchatInterne $demande, Request $request, Registry $workflows, EntityManagerInterface $em): RedirectResponse
    {
        $demande->setMotifRefus((string) $request->request->get('motif_refus', ''));

        return $this->applyTransition($demande, 'refuser_definitivement', $request, $workflows, $em);
    }

    #[Route('/{id}/annuler', name: 'open_demat_example_demande_achat_annuler', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function annuler(DemandeAchatInterne $demande, Request $request, Registry $workflows, EntityManagerInterface $em): RedirectResponse
    {
        return $this->applyTransition($demande, 'annuler', $request, $workflows, $em);
    }

    #[Route('/{id}/terminer', name: 'open_demat_example_demande_achat_terminer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_EXAMPLE_GESTIONNAIRE')]
    public function terminer(DemandeAchatInterne $demande, Request $request, Registry $workflows, EntityManagerInterface $em): RedirectResponse
    {
        return $this->applyTransition($demande, 'terminer', $request, $workflows, $em);
    }

    private function applyTransition(
        DemandeAchatInterne $demande,
        string $transition,
        Request $request,
        Registry $workflows,
        EntityManagerInterface $em,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('example_demande_achat_' . $transition . '_' . $demande->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('open_demat_example_demande_achat_show', ['id' => $demande->getId()]);
        }

        $workflow = $workflows->get($demande, 'example_demande_achat');
        if (!$workflow->can($demande, $transition)) {
            $this->addFlash('warning', 'Transition indisponible pour ce statut.');

            return $this->redirectToRoute('open_demat_example_demande_achat_show', ['id' => $demande->getId()]);
        }

        $workflow->apply($demande, $transition);
        $em->flush();

        $this->addFlash('success', 'Transition appliquee.');

        return $this->redirectToRoute('open_demat_example_demande_achat_show', ['id' => $demande->getId()]);
    }

    private function createDemandeForm(DemandeAchatInterne $demande): FormInterface
    {
        return $this->createFormBuilder($demande)
            ->add('demandeur', TextType::class, ['label' => 'Demandeur'])
            ->add('serviceDemandeur', TextType::class, ['label' => 'Service demandeur'])
            ->add('intituleBesoin', TextType::class, ['label' => 'Intitule du besoin'])
            ->add('justification', TextareaType::class, ['label' => 'Justification'])
            ->add('montantEstime', IntegerType::class, ['label' => 'Montant estime'])
            ->add('typeAchat', ChoiceType::class, [
                'label' => 'Type d achat',
                'choices' => [
                    'Fourniture' => 'FOURNITURE',
                    'Prestation' => 'PRESTATION',
                    'Logiciel' => 'LOGICIEL',
                    'Materiel' => 'MATERIEL',
                ],
            ])
            ->add('fournisseurSouhaite', TextType::class, [
                'label' => 'Fournisseur souhaite',
                'required' => false,
            ])
            ->add('centreCout', TextType::class, ['label' => 'Centre de cout'])
            ->add('dateBesoin', DateType::class, [
                'label' => 'Date de besoin',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('urgence', CheckboxType::class, [
                'label' => 'Demande urgente',
                'required' => false,
            ])
            ->add('attestations', ChoiceType::class, [
                'label' => 'Engagements',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'choices' => [
                    'Budget disponible' => 'BUDGET_OK',
                    'Besoin valide par le service' => 'BESOIN_SERVICE_OK',
                    'Achat conforme aux regles internes' => 'CONFORMITE_OK',
                ],
            ])
            ->add('pieceJointe', FileType::class, [
                'label' => 'Piece jointe',
                'mapped' => false,
                'required' => false,
                'help' => 'PDF, image ou document bureautique.',
            ])
            ->getForm();
    }

    private function getCorrectableFields(): array
    {
        return [
            'demandeur' => 'Demandeur',
            'serviceDemandeur' => 'Service demandeur',
            'intituleBesoin' => 'Intitule du besoin',
            'justification' => 'Justification',
            'montantEstime' => 'Montant estime',
            'typeAchat' => 'Type d achat',
            'fournisseurSouhaite' => 'Fournisseur souhaite',
            'centreCout' => 'Centre de cout',
            'dateBesoin' => 'Date de besoin',
            'urgence' => 'Urgence',
            'attestations' => 'Engagements',
        ];
    }
}
