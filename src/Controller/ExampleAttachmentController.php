<?php

namespace OpenDemat\ExampleBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Entity\ProcessAttachment;
use OpenDemat\Core\Service\AttachmentService;
use OpenDemat\ExampleBundle\Security\ExampleAttachmentVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ExampleAttachmentController extends AbstractController
{
    #[Route('/example/attachments/{id}/view', name: 'open_demat_example_attachment_view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function view(
        int $id,
        EntityManagerInterface $em,
        AttachmentService $attachmentService,
    ): StreamedResponse {
        /** @var ProcessAttachment|null $attachment */
        $attachment = $em->getRepository(ProcessAttachment::class)->find($id);
        if (!$attachment) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(ExampleAttachmentVoter::VIEW, $attachment);

        $document = $attachment->getDocument();
        $stream = $attachmentService->openStream($document);

        $response = new StreamedResponse(function () use ($stream): void {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        });

        $response->headers->set('Content-Type', $document->getMimeType() ?: 'application/octet-stream');

        $fileName = (string) ($document->getOriginalName() ?: 'file');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                $fileName,
                $this->asciiFallback($fileName)
            )
        );

        return $response;
    }

    private function asciiFallback(string $name): string
    {
        $fallback = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($name));
        if ($fallback === false) {
            $fallback = $name;
        }

        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $fallback) ?: 'file';
        $fallback = trim($fallback, '._-');

        return $fallback !== '' ? $fallback : 'file';
    }
}
