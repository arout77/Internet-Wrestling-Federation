<?php
namespace App\Model;

use Src\Model\System_Model;

class TraitsModel extends System_Model
{
    /**
     * Fetches all traits from the database.
     *
     * @return array
     */
    public function getAllTraits()
    {
        $sql   = "SELECT name, description FROM traits ORDER BY name ASC";
        $query = $this->db->prepare( $sql );
        $query->execute();
        return $query->fetchAll( \PDO::FETCH_ASSOC );
    }
}
