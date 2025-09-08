<?php
namespace App\Model;

use PDO;
use Src\Model\System_Model;

class UserModel extends System_Model
{
    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );
    }

    /**
     * Register a new user
     * @param string $username
     * @param string $email
     * @param string $password
     * @return bool|string
     */
    public function createNewUser( $username, $email, $password )
    {
        try {
            $stmt = $this->db->prepare( "SELECT * FROM users WHERE name = :username OR email = :email" );
            $stmt->execute( [':username' => $username, ':email' => $email] );
            if ( $stmt->fetch() )
            {
                return "Username or email already exists.";
            }

            $hashedPassword = password_hash( $password, PASSWORD_DEFAULT );
            $userId         = bin2hex( random_bytes( 16 ) );

            $sql  = "INSERT INTO users (user_id, name, email, password, prospect_id) VALUES (:user_id, :username, :email, :password, NULL)";
            $stmt = $this->db->prepare( $sql );
            $stmt->bindParam( ':user_id', $userId );
            $stmt->bindParam( ':username', $username );
            $stmt->bindParam( ':email', $email );
            $stmt->bindParam( ':password', $hashedPassword );

            if ( $stmt->execute() )
            {
                return true;
            }
            else
            {
                return "An error occurred during registration.";
            }

        }
        catch ( \PDOException $e )
        {
            return 'Database error: ' . $e->getMessage();
        }
    }

    /**
     * Verify user credentials for login
     * @param string $credential
     * @param string $password
     * @return array|false
     */
    public function verifyUser( $credential, $password )
    {
        try {
            $stmt = $this->db->prepare( "SELECT * FROM users WHERE email = :credential OR name = :credential" );
            $stmt->execute( [':credential' => $credential] );
            $user = $stmt->fetch( PDO::FETCH_ASSOC );

            if ( $user && password_verify( $password, $user['password'] ) )
            {
                return $user;
            }

            return false;

        }
        catch ( \PDOException $e )
        {
            return false;
        }
    }

    /**
     * Retrieves the gold amount for a given user by finding their associated prospect.
     * @param string $user_id
     * @return int|false
     */
    public function getGold( $user_id )
    {
        $stmt = $this->db->prepare( "SELECT p.gold
                                   FROM prospects p
                                   JOIN users u ON u.prospect_id = p.pid
                                   WHERE u.user_id = ?" );
        $stmt->execute( [$user_id] );
        $result = $stmt->fetchColumn();

        return ( $result !== false ) ? (int) $result : false;
    }

    /**
     * Updates the gold amount for a given user's associated prospect.
     * @param string $user_id
     * @param int $amount The amount to add (can be negative).
     * @return bool
     */
    public function updateGold( $user_id, $amount )
    {
        // First, find the prospect ID associated with the user ID.
        $stmt = $this->db->prepare( "SELECT prospect_id FROM users WHERE user_id = ?" );
        $stmt->execute( [$user_id] );
        $prospect_id = $stmt->fetchColumn();

        if ( $prospect_id )
        {
            // Now, update the gold in the prospects table using the correct ID.
            $update_stmt = $this->db->prepare( "UPDATE prospects SET gold = gold + ? WHERE pid = ?" );
            return $update_stmt->execute( [(int) $amount, $prospect_id] );
        }

        return false;
    }

    /**
     * Creates a new prospect and links it to a user account.
     * @param string $userId
     * @param array $prospectData
     * @return array|string
     */
    public function createProspectForUser( $userId, $prospectData )
    {
        $this->db->beginTransaction();
        try {
            $pid = bin2hex( random_bytes( 16 ) );

            $sqlProspect  = "INSERT INTO prospects (pid, name, height, weight, image, baseHp, strength, technicalAbility, brawlingAbility, stamina, aerialAbility, toughness, reversalAbility, submissionDefense, staminaRecoveryRate, moves, lvl, attribute_points) VALUES (:pid, :name, :height, :weight, :image, :baseHp, :strength, :technicalAbility, :brawlingAbility, :stamina, :aerialAbility, :toughness, :reversalAbility, :submissionDefense, :staminaRecoveryRate, :moves, 1, 5)";
            $stmtProspect = $this->db->prepare( $sqlProspect );

            $defaultMoves = json_encode( [
                "strike"     => ["Punch", "Clothesline", "Knee Drop"],
                "grapple"    => ["Body Slam", "Suplex", "Inverted atomic drop", "Abdominal Stretch", "Hip Toss", "Arm Bar"],
                "finisher"   => ["Piledriver"],
                "highFlying" => ["Dropkick"],
            ] );

            $stmtProspect->execute( [
                ':pid'                 => $pid,
                ':name'                => $prospectData['name'],
                ':height'              => $prospectData['height'],
                ':weight'              => $prospectData['weight'],
                ':image'               => $prospectData['avatar'],
                ':baseHp'              => '1000',
                ':strength'            => 50,
                ':technicalAbility'    => 50,
                ':brawlingAbility'     => 50,
                ':stamina'             => 50,
                ':aerialAbility'       => 50,
                ':toughness'           => 50,
                ':reversalAbility'     => 50,
                ':submissionDefense'   => '50',
                ':staminaRecoveryRate' => 5,
                ':moves'               => $defaultMoves,
            ] );

            $sqlUser  = "UPDATE users SET prospect_id = :pid WHERE user_id = :user_id";
            $stmtUser = $this->db->prepare( $sqlUser );
            $stmtUser->execute( [':pid' => $pid, ':user_id' => $userId] );

            $this->db->commit();

            return $this->getProspectByPid( $pid );

        }
        catch ( \PDOException $e )
        {
            $this->db->rollBack();
            return 'Database error: ' . $e->getMessage();
        }
    }

    /**
     * Fetches a prospect's data based on their PID.
     * @param string $pid
     * @return array|false
     */
    public function getProspectByPid( $pid )
    {
        try {
            $stmt = $this->db->prepare( "SELECT * FROM prospects WHERE pid = :pid" );
            $stmt->execute( [':pid' => $pid] );
            return $stmt->fetch( PDO::FETCH_ASSOC );
        }
        catch ( \PDOException $e )
        {
            return false;
        }
    }

    /**
     * Fetches a user's prospect data.
     * @param string $userId
     * @return array|false
     */
    public function getProspectByUserId( $userId )
    {
        $sql  = "SELECT p.*, u.user_id FROM prospects p JOIN users u ON p.pid = u.prospect_id WHERE u.user_id = :user_id";
        $stmt = $this->db->prepare( $sql );
        $stmt->execute( [':user_id' => $userId] );
        $prospectData = $stmt->fetch( \PDO::FETCH_ASSOC );

        if ( !$prospectData )
        {
            return null;
        }

        return [
            'id'                  => $prospectData['id'],
            'pid'                 => $prospectData['pid'],
            'name'                => $prospectData['name'],
            'height'              => $prospectData['height'],
            'weight'              => $prospectData['weight'],
            'description'         => $prospectData['description'],
            'gold'                => $prospectData['gold'],
            'current_xp'          => $prospectData['current_xp'],
            'lvl'                 => $prospectData['lvl'],
            'attribute_points'    => $prospectData['attribute_points'],
            'baseHp'              => $prospectData['baseHp'],
            'strength'            => $prospectData['strength'],
            'technicalAbility'    => $prospectData['technicalAbility'],
            'brawlingAbility'     => $prospectData['brawlingAbility'],
            'stamina'             => $prospectData['stamina'],
            'aerialAbility'       => $prospectData['aerialAbility'],
            'toughness'           => $prospectData['toughness'],
            'reversalAbility'     => $prospectData['reversalAbility'],
            'submissionDefense'   => $prospectData['submissionDefense'],
            'staminaRecoveryRate' => $prospectData['staminaRecoveryRate'],
            'moves'               => $prospectData['moves'],
            'image'               => $prospectData['image'],
            'manager_id'          => $prospectData['manager_id'],
            'user_id'             => $prospectData['user_id'],
        ];
    }

    /**
     * Fetches a wrestler's win/loss record.
     * @param string $wrestlerId
     * @return array
     */
    public function getWrestlerRecord( $wrestlerId )
    {
        try {
            $stmt = $this->db->prepare( "SELECT wins, losses FROM wrestler_records WHERE wrestler_id = :id" );
            $stmt->execute( [':id' => $wrestlerId] );
            $record = $stmt->fetch( PDO::FETCH_ASSOC );
            return $record ?: ['wins' => 0, 'losses' => 0];
        }
        catch ( \PDOException $e )
        {
            return ['wins' => 0, 'losses' => 0];
        }
    }
}
