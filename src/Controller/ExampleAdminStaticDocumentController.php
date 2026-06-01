<?php

namespace OpenDemat\ExampleBundle\Controller;

use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Service\StaticDocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/example/admin/documents-statiques', name: 'open_demat_example_admin_static_document_')]
final class ExampleAdminStaticDocumentController extends AbstractController
{
    public const DOCUMENTATION_CODE = 'example.documentation_application';

    private const DOCUMENTS = [
        self::DOCUMENTATION_CODE => [
            'label' => 'Documentation de l application',
            'description' => 'Documentation consultable depuis le formulaire de demande d achat.',
            'scope' => 'example',
            'disposition' => 'inline',
        ],
    ];

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(StaticDocumentService $staticDocuments): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EXAMPLE_POWERUSER');

        $rows = [];
        foreach (self::DOCUMENTS as $code => $config) {
            $rows[] = [
                'code' => $code,
                'label' => $config['label'],
                'description' => $config['description'],
                'document' => $staticDocuments->findActiveByCode($code),
            ];
        }

        return $this->render('@OpenDemat/example-bundle/src/templates/admin/static_documents.html.twig', [
            'rows' => $rows,
        ]);
    }

    #[Route('/{code}/upload', name: 'upload', requirements: ['code' => '.+'], methods: ['POST'])]
    public function upload(string $code, Request $request, StaticDocumentService $staticDocuments): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EXAMPLE_POWERUSER');

        if (!isset(self::DOCUMENTS[$code])) {
            throw $this->createNotFoundException('Document statique inconnu.');
        }

        if (!$this->isCsrfTokenValid('example_static_document_upload_' . $code, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('danger', 'Le fichier transmis est invalide.');

            return $this->redirectToRoute('open_demat_example_admin_static_document_list');
        }

        if ($file->getClientMimeType() !== 'application/pdf') {
            $this->addFlash('danger', 'Le document doit etre un fichier PDF.');

            return $this->redirectToRoute('open_demat_example_admin_static_document_list');
        }

        if ($file->getSize() !== false && $file->getSize() > 10 * 1024 * 1024) {
            $this->addFlash('danger', 'Le fichier ne doit pas depasser 10 Mo.');

            return $this->redirectToRoute('open_demat_example_admin_static_document_list');
        }

        $config = self::DOCUMENTS[$code];
        $user = $this->getUser();

        $staticDocuments->storeUploadedFile(
            code: $code,
            scope: $config['scope'],
            label: $config['label'],
            file: $file,
            uploadedBy: $user instanceof User ? $user : null,
            disposition: $config['disposition'],
        );

        $this->addFlash('success', 'Document statique mis a jour avec succes.');

        return $this->redirectToRoute('open_demat_example_admin_static_document_list');
    }
}
