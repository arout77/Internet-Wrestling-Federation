<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table( name: 'challenges' )]
class Challenge
{
    #[ORM\Id]
    #[ORM\Column( type: 'integer' )]
    #[ORM\GeneratedValue]
    private int $id;

    /**
     * The Prospect who issued the challenge
     */
    #[ORM\ManyToOne( targetEntity: Prospect::class )]
    #[ORM\JoinColumn( name: 'challenger_pid', referencedColumnName: 'pid' )]
    private Prospect $challenger;

    /**
     * The Prospect who received the challenge
     */
    #[ORM\ManyToOne( targetEntity: Prospect::class )]
    #[ORM\JoinColumn( name: 'defender_pid', referencedColumnName: 'pid' )]
    private Prospect $defender;

    #[ORM\Column( type: 'integer' )]
    private int $wager_amount;

    #[ORM\Column( type: 'string', length: 50, options: ['default' => 'pending'] )]
    private string $status;

    /**
     * The Prospect who won (if completed)
     */
    #[ORM\ManyToOne( targetEntity: Prospect::class )]
    #[ORM\JoinColumn( name: 'winner_pid', referencedColumnName: 'pid', nullable: true )]
    private ?Prospect $winner;

    #[ORM\Column( type: 'boolean', options: ['default' => false] )]
    private bool $is_read;

    #[ORM\Column( type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'] )]
    private \DateTime $created_at;

    #[ORM\Column( type: 'datetime', nullable: true )]
    private ?\DateTime $resolved_at;

    public function __construct()
    {
        $this->status     = 'pending';
        $this->is_read    = false;
        $this->created_at = new \DateTime();
    }

    // --- Getters and Setters ---

    /**
     * @return mixed
     */
    public function getId() : int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getChallenger(): Prospect
    {
        return $this->challenger;
    }

    /**
     * @param Prospect $challenger
     */
    public function setChallenger( Prospect $challenger ): void
    {
        $this->challenger = $challenger;
    }

    /**
     * @return mixed
     */
    public function getDefender(): Prospect
    {
        return $this->defender;
    }

    /**
     * @param Prospect $defender
     */
    public function setDefender( Prospect $defender ): void
    {
        $this->defender = $defender;
    }

    /**
     * @return mixed
     */
    public function getWagerAmount(): int
    {
        return $this->wager_amount;
    }

    /**
     * @param int $wager_amount
     */
    public function setWagerAmount( int $wager_amount ): void
    {
        $this->wager_amount = $wager_amount;
    }

    /**
     * @return mixed
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus( string $status ): void
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getWinner(): ?Prospect
    {
        return $this->winner;
    }

    /**
     * @param Prospect $winner
     */
    public function setWinner( ?Prospect $winner ): void
    {
        $this->winner = $winner;
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
     * @return mixed
     */
    public function getResolvedAt(): ?\DateTime
    {
        return $this->resolved_at;
    }

    /**
     * @param \DateTime $resolved_at
     */
    public function setResolvedAt(  ? \DateTime $resolved_at ) : void
    {
        $this->resolved_at = $resolved_at;
    }
}
