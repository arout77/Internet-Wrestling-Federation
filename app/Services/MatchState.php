<?php
namespace App\Services\Simulation;

use App\Models\Wrestler;

class MatchState
{
    public Wrestler $wrestlerA;
    public Wrestler $wrestlerB;

    // Dynamic tracking arrays mapped by Wrestler ID
    public array $stamina = [];
    public array $damage  = [];

    // Momentum slider: Positive favors Wrestler A, Negative favors Wrestler B
    public int $momentum    = 0;
    public int $currentTurn = 1;
    public array $log       = [];

    public function __construct(Wrestler $wrestlerA, Wrestler $wrestlerB)
    {
        $this->wrestlerA = $wrestlerA;
        $this->wrestlerB = $wrestlerB;

        // Initialize base match stats based on wrestler attributes
        $this->stamina[$wrestlerA->id] = $wrestlerA->base_stamina ?? 100;
        $this->stamina[$wrestlerB->id] = $wrestlerB->base_stamina ?? 100;

        $this->damage[$wrestlerA->id] = 0;
        $this->damage[$wrestlerB->id] = 0;
    }

    public function adjustMomentum(int $amount, int $wrestlerId): void
    {
        // If Wrestler A scores, push momentum positive. If B scores, push negative.
        if ($wrestlerId === $this->wrestlerA->id) {
            $this->momentum = min(100, $this->momentum + $amount);
        } else {
            $this->momentum = max(-100, $this->momentum - $amount);
        }
    }

    public function getMomentumModifier(int $wrestlerId): float
    {
        // Returns a scaling factor based on current momentum direction
        if ($wrestlerId === $this->wrestlerA->id) {
            return $this->momentum / 100.0;
        }
        return ($this->momentum * -1) / 100.0;
    }
}
