<?php

namespace App\Controllers;

use App\Entities\Challenge;
use App\Entities\Notification;
use App\Entities\Prospect;
use App\Entities\User;
use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Session;
use Doctrine\ORM\EntityManager;
use Twig\Environment;

class ChallengeController extends BaseController
{
    /**
     * @param EntityManager $em
     * @param Environment $twig
     */
    public function __construct(
        protected EntityManager $em,
        Environment $twig
    ) {
        parent::__construct( $twig );
    }

    /**
     * Creates a new challenge.
     * This is where we send the "Challenge Received" notification.
     */
    public function create( Request $request ): Response
    {
        if ( !Session::has( 'user_id' ) ) {
            return $this->json( ['error' => 'Unauthorized'], 401 );
        }

        $user               = $this->em->find( User::class, Session::get( 'user_id' ) );
        $challengerProspect = $user->getProspect();

        if ( !$challengerProspect ) {
            return $this->json( ['error' => 'You must have a prospect to send a challenge.'], 400 );
        }

        $data        = $request->getBody();
        $defenderPid = $data['defender_pid'] ?? null;
        $wager       = (int) ( $data['wager_amount'] ?? 0 );

        if ( !$defenderPid ) {
            return $this->json( ['error' => 'No defender specified.'], 400 );
        }

        $defenderProspect = $this->em->getRepository( Prospect::class )->findOneBy( ['pid' => $defenderPid] );

        if ( !$defenderProspect ) {
            return $this->json( ['error' => 'Defender prospect not found.'], 404 );
        }

        // Find the defender's User account to send them a notification
        $defenderUser = $defenderProspect->getUser();
        if ( !$defenderUser ) {
            return $this->json( ['error' => 'Could not find the owner of that prospect.'], 500 );
        }

        try {
            // 1. Create the Challenge
            $challenge = new Challenge();
            $challenge->setChallenger( $challengerProspect );
            $challenge->setDefender( $defenderProspect );
            $challenge->setWagerAmount( $wager );
            $this->em->persist( $challenge );

            // 2. CREATE THE "CHALLENGE RECEIVED" NOTIFICATION
            $notification = new Notification();
            $notification->setUser( $defenderUser ); // Send to the defender
            $notification->setMessage( "{$challengerProspect->getName()} has challenged you to a match for {$wager} Gold!" );
            $notification->setLink( '/challenges' ); // Link to the challenges page
            $this->em->persist( $notification );

            // 3. Save both to the database
            $this->em->flush();

            return $this->json( ['success' => true, 'message' => 'Challenge sent!'], 201 );

        } catch ( \Exception $e ) {
            return $this->json( ['error' => 'Server error: ' . $e->getMessage()], 500 );
        }
    }

    /**
     * Resolves a challenge (accept, decline).
     * This is where we send the "Challenge Resolved" notification.
     */
    public function resolve( Request $request ): Response
    {
        if ( !Session::has( 'user_id' ) ) {
            return $this->json( ['error' => 'Unauthorized'], 401 );
        }

        $user       = $this->em->find( User::class, Session::get( 'user_id' ) );
        $myProspect = $user->getProspect();

        $data        = $request->getBody();
        $challengeId = $data['challenge_id'] ?? null;
        $resolution  = $data['resolution'] ?? null; // 'accepted' or 'declined'

        if ( !$challengeId || !in_array( $resolution, ['accepted', 'declined'] ) ) {
            return $this->json( ['error' => 'Invalid request data.'], 400 );
        }

        $challenge = $this->em->find( Challenge::class, $challengeId );

        if ( !$challenge || $challenge->getDefender()->getPid() !== $myProspect->getPid() ) {
            return $this->json( ['error' => 'Challenge not found or you are not the defender.'], 404 );
        }

        if ( $challenge->getStatus() !== 'pending' ) {
            return $this->json( ['error' => 'This challenge has already been resolved.'], 400 );
        }

        // Find the original challenger's User account to notify them
        $challengerUser = $challenge->getChallenger()->getUser();

        try {
            // 1. Update the Challenge
            $challenge->setStatus( $resolution );
            $challenge->setResolvedAt( new \DateTime() );
            $this->em->persist( $challenge );

            // 2. CREATE THE "CHALLENGE RESOLVED" NOTIFICATION
            $notification = new Notification();
            $notification->setUser( $challengerUser ); // Send to the original challenger
            $notification->setMessage( "Your challenge against {$myProspect->getName()} was {$resolution}." );
            $notification->setLink( '/challenges' );
            $this->em->persist( $notification );

            // 3. TODO: Handle wager logic if 'accepted'

            // 4. Save both to the database
            $this->em->flush();

            return $this->json( ['success' => true, 'message' => "Challenge {$resolution}."] );

        } catch ( \Exception $e ) {
            return $this->json( ['error' => 'Server error: ' . $e->getMessage()], 500 );
        }
    }
}
