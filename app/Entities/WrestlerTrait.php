<?php

namespace App\Entities;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection; // <-- ADD THIS
use Doctrine\ORM\Mapping as ORM;
// <-- ADD THIS

#[ORM\Entity]
#[ORM\Table( name: 'traits' )]
class WrestlerTrait
{
    #[ORM\Id]
    #[ORM\Column( type: 'integer' )]
    #[ORM\GeneratedValue]
    private int $trait_id;

    #[ORM\Column( type: 'string', length: 50 )]
    private string $name;

    #[ORM\Column( type: 'string', length: 255 )]
    private string $description;

    /**
     * NEW: Inverse side of the relationship
     */
    #[ORM\ManyToMany( targetEntity: Prospect::class, mappedBy: 'traits' )]
    private Collection $prospects;

    /**
     * NEW: Inverse side of the relationship
     */
    #[ORM\ManyToMany( targetEntity: Roster::class, mappedBy: 'traits' )]
    private Collection $rosterWrestlers;

    public function __construct()
    {
        $this->prospects       = new ArrayCollection(); // <-- ADD THIS
        $this->rosterWrestlers = new ArrayCollection(); // <-- ADD THIS
    }

    // --- Getters ---

    /**
     * @return mixed
     */
    public function getTraitId(): int
    {
        return $this->trait_id;
    }

    /**
     * @return mixed
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getDescription(): string
    {
        return $this->description;
    }
}
