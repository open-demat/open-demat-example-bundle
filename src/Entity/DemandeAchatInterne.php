<?php

namespace OpenDemat\ExampleBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Process\AbstractProcessEntity;
use OpenDemat\Core\Process\Attribute\ProcessDefinition;

#[ProcessDefinition(
    key: 'example_demande_achat',
    label: 'Exemple - Demande d achat interne',
    roles: ['ROLE_EXAMPLE_OPE'],
    listFields: [
        'id',
        'statutTraitement',
        'demandeur',
        'serviceDemandeur',
        'intituleBesoin',
        'montantEstime',
        'typeAchat',
        'centreCout',
        'dateBesoin',
        'urgence',
    ],
    formFields: [
        'demandeur',
        'serviceDemandeur',
        'intituleBesoin',
        'justification',
        'montantEstime',
        'typeAchat',
        'fournisseurSouhaite',
        'centreCout',
        'dateBesoin',
        'urgence',
        'attestations',
        'commentaireGestionnaire',
    ],
    workflow: 'example_demande_achat'
)]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'demande_achat_interne', schema: 'example')]
#[ORM\AttributeOverrides([
    new ORM\AttributeOverride(
        name: 'statut',
        column: new ORM\Column(name: 'statut_traitement', type: Types::STRING, length: 50)
    ),
])]
class DemandeAchatInterne extends AbstractProcessEntity
{
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'auteur_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $auteur = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $demandeur = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $serviceDemandeur = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $intituleBesoin = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $justification = '';

    #[ORM\Column(type: Types::INTEGER)]
    private int $montantEstime = 0;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $typeAchat = 'FOURNITURE';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $fournisseurSouhaite = null;

    #[ORM\Column(type: Types::STRING, length: 80)]
    private string $centreCout = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateBesoin = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $urgence = false;

    #[ORM\Column(type: Types::JSON)]
    private array $attestations = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaireGestionnaire = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motifCorrection = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motifRefus = null;

    #[ORM\Column(name: 'champs_a_corriger', type: Types::JSON, options: ['default' => '[]'])]
    private array $champsACorriger = [];

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateFinProcess = null;

    public function __construct()
    {
        parent::__construct();

        $this->statut = 'brouillon';
        $this->dateBesoin = new \DateTimeImmutable('+15 days');
    }

    #[ORM\PrePersist]
    public function initializeStatutTraitement(): void
    {
        if ($this->statut === 'DRAFT') {
            $this->statut = 'brouillon';
        }
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): self
    {
        $this->auteur = $auteur;
        $this->touch();

        return $this;
    }

    public function getStatutTraitement(): string
    {
        return $this->getStatut();
    }

    public function setStatutTraitement(string $statutTraitement): self
    {
        return $this->setStatut($statutTraitement);
    }

    public function getDemandeur(): string
    {
        return $this->demandeur;
    }

    public function setDemandeur(string $demandeur): self
    {
        $this->demandeur = trim($demandeur);
        $this->touch();

        return $this;
    }

    public function getServiceDemandeur(): string
    {
        return $this->serviceDemandeur;
    }

    public function setServiceDemandeur(string $serviceDemandeur): self
    {
        $this->serviceDemandeur = trim($serviceDemandeur);
        $this->touch();

        return $this;
    }

    public function getIntituleBesoin(): string
    {
        return $this->intituleBesoin;
    }

    public function setIntituleBesoin(string $intituleBesoin): self
    {
        $this->intituleBesoin = trim($intituleBesoin);
        $this->touch();

        return $this;
    }

    public function getJustification(): string
    {
        return $this->justification;
    }

    public function setJustification(string $justification): self
    {
        $this->justification = trim($justification);
        $this->touch();

        return $this;
    }

    public function getMontantEstime(): int
    {
        return $this->montantEstime;
    }

    public function setMontantEstime(int $montantEstime): self
    {
        $this->montantEstime = max(0, $montantEstime);
        $this->touch();

        return $this;
    }

    public function getTypeAchat(): string
    {
        return $this->typeAchat;
    }

    public function setTypeAchat(string $typeAchat): self
    {
        $this->typeAchat = trim($typeAchat);
        $this->touch();

        return $this;
    }

    public function getFournisseurSouhaite(): ?string
    {
        return $this->fournisseurSouhaite;
    }

    public function setFournisseurSouhaite(?string $fournisseurSouhaite): self
    {
        $this->fournisseurSouhaite = $fournisseurSouhaite !== null ? trim($fournisseurSouhaite) : null;
        $this->touch();

        return $this;
    }

    public function getCentreCout(): string
    {
        return $this->centreCout;
    }

    public function setCentreCout(string $centreCout): self
    {
        $this->centreCout = trim($centreCout);
        $this->touch();

        return $this;
    }

    public function getDateBesoin(): ?\DateTimeImmutable
    {
        return $this->dateBesoin;
    }

    public function setDateBesoin(?\DateTimeImmutable $dateBesoin): self
    {
        $this->dateBesoin = $dateBesoin;
        $this->touch();

        return $this;
    }

    public function isUrgence(): bool
    {
        return $this->urgence;
    }

    public function getUrgence(): bool
    {
        return $this->urgence;
    }

    public function setUrgence(bool $urgence): self
    {
        $this->urgence = $urgence;
        $this->touch();

        return $this;
    }

    public function getAttestations(): array
    {
        return $this->attestations;
    }

    public function setAttestations(array $attestations): self
    {
        $attestations = array_values(array_unique(array_map('strval', $attestations)));
        $this->attestations = $attestations;
        $this->touch();

        return $this;
    }

    public function addAttestation(string $key): self
    {
        if (!in_array($key, $this->attestations, true)) {
            $this->attestations[] = $key;
            $this->touch();
        }

        return $this;
    }

    public function removeAttestation(string $key): self
    {
        $this->attestations = array_values(array_filter(
            $this->attestations,
            static fn (string $value): bool => $value !== $key
        ));
        $this->touch();

        return $this;
    }

    public function hasAttestation(string $key): bool
    {
        return in_array($key, $this->attestations, true);
    }

    public function getCommentaireGestionnaire(): ?string
    {
        return $this->commentaireGestionnaire;
    }

    public function setCommentaireGestionnaire(?string $commentaireGestionnaire): self
    {
        $this->commentaireGestionnaire = $commentaireGestionnaire !== null ? trim($commentaireGestionnaire) : null;
        $this->touch();

        return $this;
    }

    public function getMotifCorrection(): ?string
    {
        return $this->motifCorrection;
    }

    public function setMotifCorrection(?string $motifCorrection): self
    {
        $this->motifCorrection = $motifCorrection !== null ? trim($motifCorrection) : null;
        $this->touch();

        return $this;
    }

    public function getMotifRefus(): ?string
    {
        return $this->motifRefus;
    }

    public function setMotifRefus(?string $motifRefus): self
    {
        $this->motifRefus = $motifRefus !== null ? trim($motifRefus) : null;
        $this->touch();

        return $this;
    }

    public function getChampsACorriger(): array
    {
        return $this->champsACorriger;
    }

    public function setChampsACorriger(array $champsACorriger): self
    {
        $this->champsACorriger = array_values(array_unique(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            $champsACorriger
        ))));
        $this->touch();

        return $this;
    }

    public function hasChampACorriger(string $champ): bool
    {
        return in_array($champ, $this->champsACorriger, true);
    }

    public function getDateFinProcess(): ?\DateTimeImmutable
    {
        return $this->dateFinProcess;
    }

    public function setDateFinProcess(?\DateTimeImmutable $dateFinProcess): self
    {
        $this->dateFinProcess = $dateFinProcess;
        $this->touch();

        return $this;
    }
}
