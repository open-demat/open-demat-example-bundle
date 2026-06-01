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
