<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table( name: 'prospect_nicknames' )]
class ProspectNickname
{
    #[ORM\Id]
    #[ORM\Column( type: 'string', length: 50 )]
    private string $nickname;

    /**
     * @return mixed
     */
    public function getNickname(): string
    {
        return $this->nickname;
    }
}
