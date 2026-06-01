<?php

namespace App\Entities;

use App\Entities\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table( name: 'notifications' )]
class Notification
{
    #[ORM\Id]
    #[ORM\Column( type: 'integer' )]
    #[ORM\GeneratedValue]
    private int $id;

    /**
     * The user this notification is for.
     */
    #[ORM\ManyToOne( targetEntity: User::class, inversedBy: 'notifications' )]
    #[ORM\JoinColumn( name: 'user_id', referencedColumnName: 'user_id' )]
    private User $user;

    #[ORM\Column( type: 'string', length: 255 )]
    private string $message;

    #[ORM\Column( type: 'string', length: 255, nullable: true )]
    private ?string $link; // e.g., '/challenges'

    #[ORM\Column( type: 'boolean', options: ['default' => false] )]
    private bool $is_read;

    #[ORM\Column( type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'] )]
    private \DateTime $created_at;

    public function __construct()
    {
        $this->is_read    = false;
        $this->created_at = new \DateTime();
    }

    // --- Getters and Setters ---

    /**
     * @return mixed
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser( User $user ): void
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage( string $message ): void
    {
        $this->message = $message;
    }

    /**
     * @return mixed
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * @param string $link
     */
    public function setLink( ?string $link ): void
    {
        $this->link = $link;
    }

    /**
     * @return mixed
     */
    public function isIsRead(): bool
    {
        return $this->is_read;
    }

    /**
     * @param bool $is_read
     */
    public function setIsRead( bool $is_read ): void
    {
        $this->is_read = $is_read;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->created_at;
    }

    /**
     * Helper to return a user-friendly "time ago" string.
     */
    public function getTimeAgo(): string
    {
        $diff = ( new \DateTime() )->getTimestamp() - $this->created_at->getTimestamp();

        if ( $diff < 60 ) {
            return $diff . 's ago';
        } elseif ( $diff < 3600 ) {
            return floor( $diff / 60 ) . 'm ago';
        } elseif ( $diff < 86400 ) {
            return floor( $diff / 3600 ) . 'h ago';
        } else {
            return floor( $diff / 86400 ) . 'd ago';
        }
    }
}
