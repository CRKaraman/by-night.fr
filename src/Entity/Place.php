<?php

namespace App\Entity;

use App\App\Location;
use App\Geolocalize\GeolocalizeInterface;
use App\Reject\Reject;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Place.
 *
 * @ORM\Table(name="Place", indexes={
 *     @ORM\Index(name="place_nom_idx", columns={"nom"}),
 *     @ORM\Index(name="place_slug_idx", columns={"slug"}),
 *     @ORM\Index(name="place_external_id_idx", columns={"external_id"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ExclusionPolicy("all")
 * @ORM\Entity(repositoryClass="App\Repository\PlaceRepository")
 */
class Place implements GeolocalizeInterface
{
    use EntityTimestampableTrait;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"list_event"})
     * @Expose
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    protected $externalId;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=127, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private $rue;

    /**
     * @var float
     *
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private $latitude;

    /**
     * @var float
     *
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private $longitude;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank(message="Vous devez indiquer le lieu de votre événement")
     * @Groups({"list_event"})
     * @Expose
     */
    private $nom;

    /**
     * @var string
     * @Gedmo\Slug(fields={"nom"})
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $slug;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $path;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $url;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $ville;

    /**
     * @ORM\Column(type="string", length=7, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $codePostal;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=256, nullable=true)
     */
    protected $facebookId;

    /**
     * @var City|null
     * @ORM\ManyToOne(targetEntity="App\Entity\City", fetch="EAGER")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $city;

    /**
     * @var ZipCity|null
     */
    protected $zipCity;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Country")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $country;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $isJunk;

    /**
     * @var string
     */
    protected $countryName;

    /**
     * @var Reject
     */
    protected $reject;

    /** @var Location */
    protected $location;

    public function getLocation(): Location {
        if(null !== $this->location) {
            return $this->location;
        }

        $location = new Location();
        $location->setCity($this->city);
        $location->setCountry($this->country);
        return $this->location = $location;
    }

    public function getLocationSlug(): string
    {
        return $this->getLocation()->getSlug();
    }

    public function setReject(Reject $reject = null): self
    {
        $this->reject = $reject;

        return $this;
    }

    public function getReject(): ?Reject
    {
        return $this->reject;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getRue(): ?string
    {
        return $this->rue;
    }

    public function setRue(?string $rue): self
    {
        $this->rue = $rue;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): self
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getFacebookId(): ?string
    {
        return $this->facebookId;
    }

    public function setFacebookId(?string $facebookId): self
    {
        $this->facebookId = $facebookId;

        return $this;
    }

    public function getIsJunk(): ?bool
    {
        return $this->isJunk;
    }

    public function setIsJunk(?bool $isJunk): self
    {
        $this->isJunk = $isJunk;

        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): self
    {
        $this->country = $country;

        return $this;
    }
}
