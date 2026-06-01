<?php

namespace OpenDemat\ExampleBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Entity\ProcessAttachment;
use OpenDemat\Core\Entity\User;
use OpenDemat\ExampleBundle\Entity\DemandeAchatInterne;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ExampleAttachmentVoter extends Voter
{
    public const VIEW = 'EXAMPLE_ATTACHMENT_VIEW';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof ProcessAttachment;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null
    ): bool {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var ProcessAttachment $attachment */
        $attachment = $subject;
        if (
            $attachment->getProcessName() !== 'EXAMPLE'
            || $attachment->getCaseType() !== 'EXAMPLE_demande_achat'
        ) {
            return false;
        }

        /** @var DemandeAchatInterne|null $demande */
        $demande = $this->em->getRepository(DemandeAchatInterne::class)->find((int) $attachment->getCaseId());
        if (!$demande) {
            return false;
        }

        if ($this->security->isGranted('ROLE_EXAMPLE_GESTIONNAIRE')) {
            return true;
        }

        return $demande->getAuteur() instanceof User
            && $demande->getAuteur()->getId() === $user->getId();
    }
}
