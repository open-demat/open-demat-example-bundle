# Open Demat Example Bundle

Bundle d'exemple pour Open Demat.

Il declare un processus metier volontairement classique: une demande d'achat interne.

## Circuit

- `soumise` : demande en attente de traitement.
- `validee` : demande acceptee par le gestionnaire.
- `refusee` : demande refusee par le gestionnaire.
- `annulee` : demande annulee avant decision finale.
- `terminee` : demande validee puis cloturee.

## Roles

- `ROLE_EXAMPLE_DEMANDEUR`
- `ROLE_EXAMPLE_GESTIONNAIRE`
- `ROLE_EXAMPLE_OPE`
- `ROLE_EXAMPLE_ADMIN`

## Entree Open Demat

Le bundle expose l'application Hub `EXAMPLE` et le processus `example_demande_achat`.

L'application hote doit activer `OpenDemat\ExampleBundle\ExampleBundle` et installer le package Composer `open-demat/example-bundle`.

## Notifications et compatibilité Core

Les notifications de suivi du workflow utilisent `MailerService::sendBundleNotification()`
avec la clé Hub `EXAMPLE`. Le mode silencieux enregistré dans le profil supprime
les e-mails de suivi et conserve leur copie dans la messagerie interne. Sans préférence,
les e-mails restent actifs. Les futurs messages transactionnels indispensables doivent
utiliser `sendTemplated()` ou `sendToTarget()`.

Ce fonctionnement nécessite le Core Open Demat incluant la messagerie interne et
les migrations `Version20261001170000` et `Version20261001180000`.

Les pièces jointes sont téléchargées avec `application/octet-stream`, `nosniff`
et un cache privé désactivé, après contrôle du propriétaire ou du gestionnaire.
Le type MIME fourni lors de l’upload ne permet pas de rendre du HTML dans le portail.

Le projet BPM UPEC Core ne contient pas de bundle Exemple : cette adaptation
illustre l’usage de ses évolutions génériques portées dans Open Demat.
