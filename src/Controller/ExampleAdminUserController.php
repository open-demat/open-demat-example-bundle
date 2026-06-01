<?php

namespace OpenDemat\ExampleBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Repository\UserRepository;
use OpenDemat\ExampleBundle\Form\ExampleUserType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/example/admin/utilisateurs', name: 'open_demat_example_admin_user_')]
final class ExampleAdminUserController extends AbstractController
{
    private const POWERUSER_ROLE = 'ROLE_EXAMPLE_POWERUSER';

    private const ALLOWED_EXAMPLE_ROLES = [
        'ROLE_EXAMPLE_GESTIONNAIRE',
    ];

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted(self::POWERUSER_ROLE);

        return $this->render('@OpenDemat/example-bundle/src/templates/admin/user_list.html.twig', [
            'users' => $userRepository->findByProcessName('EXAMPLE', excludePowerUser: true),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted(self::POWERUSER_ROLE);

        $user = new User();
        $form = $this->createForm(ExampleUserType::class, $user, [
            'is_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedRoles = $form->get('roleExample')->getData() ?? [];

            if (!$this->areAllowedRoles($selectedRoles, self::ALLOWED_EXAMPLE_ROLES)) {
                $this->addFlash('danger', 'Un ou plusieurs roles Example sont invalides.');

                return $this->redirectToRoute('open_demat_example_admin_user_new');
            }

            if ($selectedRoles === []) {
                $this->addFlash('danger', 'Veuillez selectionner au moins un role Example.');

                return $this->redirectToRoute('open_demat_example_admin_user_new');
            }

            $existingUser = $userRepository->findOneBy(['username' => $user->getUsername()]);

            if ($existingUser instanceof User) {
                if (in_array(self::POWERUSER_ROLE, $existingUser->getRoles(), true)) {
                    $this->addFlash('danger', 'Impossible de modifier un administrateur Example via cet ecran.');

                    return $this->redirectToRoute('open_demat_example_admin_user_list');
                }

                $existingUser->setRoles(array_values(array_unique(array_merge(
                    $existingUser->getRoles(),
                    $selectedRoles
                ))));

                if (!$existingUser->getEmail() && $user->getEmail()) {
                    $existingUser->setEmail($user->getEmail());
                }

                $em->flush();

                $this->addFlash('success', 'Utilisateur existant trouve : roles Example ajoutes.');

                return $this->redirectToRoute('open_demat_example_admin_user_list');
            }

            $user->setRoles(array_values(array_unique($selectedRoles)));

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Utilisateur cree avec succes.');

            return $this->redirectToRoute('open_demat_example_admin_user_list');
        }

        return $this->render('@OpenDemat/example-bundle/src/templates/admin/user_form.html.twig', [
            'form' => $form->createView(),
            'is_edit' => false,
            'user_entity' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(self::POWERUSER_ROLE);

        if (in_array(self::POWERUSER_ROLE, $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Impossible de modifier un administrateur Example ici.');
        }

        $form = $this->createForm(ExampleUserType::class, $user, [
            'is_edit' => true,
        ]);
        $form->get('roleExample')->setData($this->extractManagedExampleRoles($user->getRoles()));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedRoles = $form->get('roleExample')->getData() ?? [];

            if (!$this->areAllowedRoles($selectedRoles, self::ALLOWED_EXAMPLE_ROLES)) {
                $this->addFlash('danger', 'Un ou plusieurs roles Example sont invalides.');

                return $this->redirectToRoute('open_demat_example_admin_user_edit', ['id' => $user->getId()]);
            }

            if ($selectedRoles === []) {
                $this->addFlash('danger', 'Veuillez selectionner au moins un role Example.');

                return $this->redirectToRoute('open_demat_example_admin_user_edit', ['id' => $user->getId()]);
            }

            $nonExampleRoles = array_values(array_filter(
                $user->getRoles(),
                static fn (string $role): bool => !str_starts_with($role, 'ROLE_EXAMPLE_')
            ));

            $user->setRoles(array_values(array_unique(array_merge($nonExampleRoles, $selectedRoles))));
            $em->flush();

            $this->addFlash('success', 'Utilisateur modifie avec succes.');

            return $this->redirectToRoute('open_demat_example_admin_user_list');
        }

        return $this->render('@OpenDemat/example-bundle/src/templates/admin/user_form.html.twig', [
            'form' => $form->createView(),
            'is_edit' => true,
            'user_entity' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(self::POWERUSER_ROLE);

        if (!$this->isCsrfTokenValid('example_delete_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        if (in_array(self::POWERUSER_ROLE, $user->getRoles(), true)) {
            $this->addFlash('danger', 'Impossible de modifier un administrateur Example ici.');

            return $this->redirectToRoute('open_demat_example_admin_user_list');
        }

        $remainingRoles = array_values(array_filter(
            $user->getRoles(),
            static fn (string $role): bool => !str_starts_with($role, 'ROLE_EXAMPLE_')
        ));

        $user->setRoles($remainingRoles);
        $em->flush();

        $this->addFlash('success', 'Les roles Example de l utilisateur ont ete supprimes.');

        return $this->redirectToRoute('open_demat_example_admin_user_list');
    }

    private function extractManagedExampleRoles(array $roles): array
    {
        return array_values(array_filter(
            $roles,
            static fn (string $role): bool => in_array($role, self::ALLOWED_EXAMPLE_ROLES, true)
        ));
    }

    private function areAllowedRoles(array $selectedRoles, array $allowedRoles): bool
    {
        foreach ($selectedRoles as $role) {
            if (!in_array($role, $allowedRoles, true)) {
                return false;
            }
        }

        return true;
    }
}
