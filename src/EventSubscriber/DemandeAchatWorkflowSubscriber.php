<?php

namespace OpenDemat\ExampleBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Entity\Task;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Mailer\Service\MailerService;
use OpenDemat\Core\Repository\TaskRepository;
use OpenDemat\ExampleBundle\Entity\DemandeAchatInterne;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\Event\Event;

class DemandeAchatWorkflowSubscriber implements EventSubscriberInterface
{
    private const PROCESS_NAME = 'EXAMPLE';
    private const CASE_TYPE = 'EXAMPLE_demande_achat_interne';

    private const ROLE_GESTIONNAIRE = 'ROLE_EXAMPLE_GESTIONNAIRE';

    private const TASK_A_TRAITER = 'a_traiter';
    private const TASK_A_CORRIGER = 'a_corriger';

    private const ALL_TASKS = [
        self::TASK_A_TRAITER,
        self::TASK_A_CORRIGER,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskRepository $taskRepository,
        private readonly Security $security,
        private readonly MailerService $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.example_demande_achat.completed.soumettre' => 'onSoumettre',
            'workflow.example_demande_achat.completed.demander_correction' => 'onDemanderCorrection',
            'workflow.example_demande_achat.completed.valider' => 'onValider',
            'workflow.example_demande_achat.completed.refuser_definitivement' => 'onRefuserDefinitivement',
            'workflow.example_demande_achat.completed.annuler' => 'onAnnuler',
            'workflow.example_demande_achat.completed.terminer' => 'onTerminer',
        ];
    }

    public function onSoumettre(Event $event): void
    {
        $subject = $event->getSubject();

        if (!$subject instanceof DemandeAchatInterne) {
            return;
        }

        $transition = $event->getTransition();
        $motifCorrection = trim((string) $subject->getMotifCorrection());
        $wasCorrection = in_array(self::TASK_A_CORRIGER, $transition->getFroms(), true)
            || $motifCorrection !== '';

        $this->closeAllTasks($subject);

        $subject->setMotifCorrection(null);
        $subject->setChampsACorriger([]);

        $task = $this->createRoleTask($subject, self::TASK_A_TRAITER, self::ROLE_GESTIONNAIRE);
        $this->em->flush();

        if ($wasCorrection) {
            $this->notifyCorrectionFaite($task, $subject, $motifCorrection);

            return;
        }

        $this->notifyNewTask($task, $subject, 'Exemple achats - Nouvelle demande a traiter');
        $this->sendSubmissionRecapToDemandeur($subject);
    }

    public function onDemanderCorrection(Event $event): void
    {
        $subject = $event->getSubject();

        if (!$subject instanceof DemandeAchatInterne) {
            return;
        }

        $this->closeAllTasks($subject);

        $task = (new Task())
            ->setProcessName(self::PROCESS_NAME)
            ->setCaseType(self::CASE_TYPE)
            ->setCaseId((int) $subject->getId())
            ->setTaskName(self::TASK_A_CORRIGER)
            ->setSummary($this->buildSummary($subject))
            ->setUser($subject->getAuteur())
            ->setActionRoute($this->buildDemandeUrl($subject));

        $this->em->persist($task);
        $this->em->flush();

        $this->notifyDemandeur(
            $subject,
            'Exemple achats - Modification demandee',
            '@OpenDemat/example-bundle/src/templates/emails/demande_notification.html.twig'
        );
    }

    public function onValider(Event $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof DemandeAchatInterne) {
            return;
        }

        $this->closeAllTasks($subject);
        $this->markProcessAsFinished($subject);

        $this->em->flush();

        $this->notifyDemandeur(
            $subject,
            'Exemple achats - Votre demande a ete validee',
            '@OpenDemat/example-bundle/src/templates/emails/demande_notification.html.twig'
        );
    }

    public function onRefuserDefinitivement(Event $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof DemandeAchatInterne) {
            return;
        }

        $this->closeAllTasks($subject);
        $this->markProcessAsFinished($subject);

        $this->em->flush();

        $this->notifyDemandeur(
            $subject,
            'Exemple achats - Votre demande a ete refusee',
            '@OpenDemat/example-bundle/src/templates/emails/demande_notification.html.twig'
        );
    }

    public function onAnnuler(Event $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof DemandeAchatInterne) {
            return;
        }

        $this->closeAllTasks($subject);
        $this->markProcessAsFinished($subject);
        $this->em->flush();

        $this->notifyDemandeur(
            $subject,
            'Exemple achats - Votre demande a ete annulee',
            '@OpenDemat/example-bundle/src/templates/emails/demande_notification.html.twig'
        );
    }

    public function onTerminer(Event $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof DemandeAchatInterne) {
            return;
        }

        $this->closeAllTasks($subject);
        $this->markProcessAsFinished($subject);

        $this->em->flush();

        $this->notifyDemandeur(
            $subject,
            'Exemple achats - Demande cloturee',
            '@OpenDemat/example-bundle/src/templates/emails/demande_notification.html.twig'
        );
    }

    private function closeAllTasks(DemandeAchatInterne $entity): void
    {
        if (null === $entity->getId()) {
            return;
        }

        $this->taskRepository->completeOpenForCaseAndTaskNames(
            self::CASE_TYPE,
            $entity->getId(),
            self::ALL_TASKS
        );
    }

    private function markProcessAsFinished(DemandeAchatInterne $entity): void
    {
        $entity->setDateFinProcess(new \DateTimeImmutable());
    }

    private function createRoleTask(DemandeAchatInterne $entity, string $taskName, string $roleName): Task
    {
        $task = (new Task())
            ->setProcessName(self::PROCESS_NAME)
            ->setCaseType(self::CASE_TYPE)
            ->setCaseId((int) $entity->getId())
            ->setTaskName($taskName)
            ->setSummary($this->buildSummary($entity))
            ->setRoleName($roleName)
            ->setActionRoute($this->buildDemandeUrl($entity));

        $this->em->persist($task);

        return $task;
    }

    private function buildSummary(DemandeAchatInterne $entity): string
    {
        $parts = [];

        if ('' !== trim($entity->getDemandeur())) {
            $parts[] = $entity->getDemandeur();
        }

        if ('' !== trim($entity->getIntituleBesoin())) {
            $parts[] = mb_strimwidth($entity->getIntituleBesoin(), 0, 60, '...');
        }

        if ($entity->getMontantEstime() > 0) {
            $parts[] = $entity->getMontantEstime() . ' EUR';
        }

        if (null !== $entity->getDateBesoin()) {
            $parts[] = $entity->getDateBesoin()->format('d/m/Y');
        }

        return [] !== $parts ? implode(' - ', $parts) : 'Demande #' . $entity->getId();
    }

    private function buildDemandeUrl(DemandeAchatInterne $entity): string
    {
        return $this->urlGenerator->generate(
            'open_demat_example_demande_achat_show',
            ['id' => $entity->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function getCurrentUser(): ?User
    {
        $tokenUser = $this->security->getUser();
        if (!$tokenUser instanceof User) {
            return null;
        }

        $id = $tokenUser->getId();
        if (null === $id) {
            return null;
        }

        return $this->em->getReference(User::class, $id);
    }

    private function notifyNewTask(Task $task, DemandeAchatInterne $entity, string $title): void
    {
        $target = (string) $task->getRoleName();
        if ($target === '') {
            return;
        }

        $this->mailer->sendToTarget(
            $target,
            $title,
            '@OpenDemat/example-bundle/src/templates/emails/task_created_simple.html.twig',
            [
                'task' => $task,
                'demande' => $entity,
                'url' => $task->getActionRoute(),
                'title' => $title,
                'notif_type' => (string) $task->getTaskName(),
                'currentUser' => $this->getCurrentUser(),
            ]
        );
    }

    private function notifyCorrectionFaite(Task $task, DemandeAchatInterne $entity, string $motifCorrection): void
    {
        $target = (string) $task->getRoleName();

        if ($target === '') {
            return;
        }

        $this->mailer->sendToTarget(
            $target,
            'Exemple achats - Correction deposee par le demandeur',
            '@OpenDemat/example-bundle/src/templates/emails/task_created_simple.html.twig',
            [
                'task' => $task,
                'demande' => $entity,
                'url' => $task->getActionRoute(),
                'title' => 'Exemple achats - Correction deposee par le demandeur',
                'motifCorrection' => $motifCorrection,
                'notif_type' => (string) $task->getTaskName(),
                'currentUser' => $this->getCurrentUser(),
            ]
        );
    }

    private function notifyDemandeur(DemandeAchatInterne $entity, string $title, string $template): void
    {
        $email = $this->resolveDemandeurEmail($entity);

        if ($email === null) {
            return;
        }

        $this->mailer->sendToTarget(
            $email,
            $title,
            $template,
            [
                'demande' => $entity,
                'url' => $this->buildDemandeUrl($entity),
                'title' => $title,
                'currentUser' => $this->getCurrentUser(),
            ]
        );
    }

    private function sendSubmissionRecapToDemandeur(DemandeAchatInterne $entity): void
    {
        $this->notifyDemandeur(
            $entity,
            'Exemple achats - Recapitulatif de votre demande',
            '@OpenDemat/example-bundle/src/templates/emails/demande_notification.html.twig'
        );
    }

    private function resolveDemandeurEmail(DemandeAchatInterne $entity): ?string
    {
        $auteur = $entity->getAuteur();

        if (!$auteur instanceof User) {
            return null;
        }

        $email = trim((string) $auteur->getEmail());

        return $email !== '' ? $email : null;
    }
}
