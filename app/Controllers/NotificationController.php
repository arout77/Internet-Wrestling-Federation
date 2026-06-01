<?php

namespace App\Controllers;

use App\Entities\Notification;
use App\Entities\User; // <-- THE FIX
use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Session;
use Doctrine\ORM\EntityManager;
use Twig\Environment;

class NotificationController extends BaseController
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
     * Fetches all unread notifications for the logged-in user.
     * This will be called by your frontend.
     */
    public function getUnread( Request $request ): Response
    {
        if ( !Session::has( 'user_id' ) ) {
            return $this->json( ['error' => 'Unauthorized'], 401 );
        }

        $userId = Session::get( 'user_id' );
        $user   = $this->em->find( User::class, $userId );

        if ( !$user ) {
            return $this->json( ['error' => 'User not found'], 404 );
        }

        $notificationRepo = $this->em->getRepository( Notification::class );

        // Find all notifications for this user, ordered by most recent
        $notifications = $notificationRepo->findBy(
            ['user' => $user, 'is_read' => false],
            ['created_at' => 'DESC']
        );

        // Format the data for a clean JSON response
        $data = array_map( function ( Notification $n ) {
            return [
                'id'       => $n->getId(),
                'message'  => $n->getMessage(),
                'link'     => $n->getLink(),
                'time_ago' => $n->getTimeAgo(),
            ];
        }, $notifications );

        return $this->json( [
            'count'         => count( $data ),
            'notifications' => $data,
        ] );
    }

    /**
     * Marks a list of notifications as read.
     */
    public function markAsRead( Request $request ): Response
    {
        if ( !Session::has( 'user_id' ) ) {
            return $this->json( ['error' => 'Unauthorized'], 401 );
        }

        $data = $request->getBody();
        $ids  = $data['ids'] ?? [];

        if ( empty( $ids ) ) {
            return $this->json( ['success' => true] ); // Nothing to do
        }

        $userId           = Session::get( 'user_id' );
        $notificationRepo = $this->em->getRepository( Notification::class );

        // Find notifications that match the IDs and belong to this user
        $notifications = $notificationRepo->createQueryBuilder( 'n' )
                                          ->where( 'n.user = :user' )
                                          ->andWhere( 'n.id IN (:ids)' )
                                          ->setParameter( 'user', $userId )
                                          ->setParameter( 'ids', $ids )
                                          ->getQuery()
                                          ->getResult();

        foreach ( $notifications as $notification ) {
            $notification->setIsRead( true );
            $this->em->persist( $notification );
        }

        $this->em->flush();

        return $this->json( ['success' => true] );
    }
}
