<?php

namespace App\Entities;

use App\Entities\Notification;
use App\Entities\Prospect;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table( name: 'users' )]
class User
{
    #[ORM\Id]
    #[ORM\Column( type: 'string', length: 255 )]
    private string $user_id;

    #[ORM\Column( type: 'string', length: 50 )]
    private string $name;

    #[ORM\Column( type: 'string', length: 100, unique: true )]
    private string $email;

    #[ORM\Column( type: 'string', length: 255 )]
    private string $password;

    /**
     * UPDATED: This now maps to the Prospect entity.
     * The `users.prospect_id` column joins to `prospects.pid`.
     */
    #[ORM\OneToOne( targetEntity: Prospect::class, cascade: ['persist', 'remove'] )]
    #[ORM\JoinColumn( name: 'prospect_id', referencedColumnName: 'pid' )]
    private ?Prospect $prospect;

    #[ORM\Column( type: 'integer', options: ['default' => 0] )]
    private int $gold;

    // --- NEW TOKENS PROPERTY ---
    #[ORM\Column( type: 'integer', options: ['default' => 0] )]
    private int $tokens;
    // ---------------------------

    #[ORM\Column( type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'] )]
    private \DateTime $created_at;

    /**
     * One-to-Many relationship with Notifications.
     * 'user' is the property name in the Notification entity.
     */
    #[ORM\OneToMany( mappedBy: 'user', targetEntity: \App\Entities\Notification::class, cascade: ['persist', 'remove'] )]
    private Collection $notifications;

    public function __construct()
    {
        $this->user_id       = bin2hex( random_bytes( 16 ) );
        $this->created_at    = new \DateTime();
        $this->gold          = 0;
        $this->tokens        = 0; // Initialize tokens
        $this->prospect      = null; // Initialize relationship as null
        $this->notifications = new ArrayCollection();
    }

    // --- Getters and Setters ---

    /**
     * @return mixed
     */
    public function getUserId(): string
    {
        return $this->user_id;
    }

    /**
     * @return mixed
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName( string $name ): void
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail( string $email ): void
    {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword( string $password ): void
    {
        $this->password = password_hash( $password, PASSWORD_BCRYPT );
    }

    /**
     * UPDATED: Getter for the Prospect object
     */
    public function getProspect(): ?Prospect
    {
        return $this->prospect;
    }

    /**
     * UPDATED: Setter for the Prospect object
     */
    public function setProspect( ?Prospect $prospect ): void
    {
        $this->prospect = $prospect;
    }

    /**
     * @return mixed
     */
    public function getGold(): int
    {
        return $this->gold;
    }

    /**
     * @param int $gold
     */
    public function setGold( int $gold ): void
    {
        $this->gold = $gold;
    }

    // --- NEW GETTER AND SETTER FOR TOKENS ---
    /**
     * @return mixed
     */
    public function getTokens(): int
    {
        return $this->tokens;
    }

    /**
     * @param int $tokens
     */
    public function setTokens( int $tokens ): void
    {
        $this->tokens = $tokens;
    }
    // ----------------------------------------

    /**
     * @return Collection|Notification[]
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }
}
