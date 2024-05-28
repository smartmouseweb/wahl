<?php
namespace Model;

use Service\DB;

class CollectionToCollection extends DB
{
    public static $dbTable = 'collection_to_collection';
    
    private int $parentId = 0;
    private int $childId = 0;

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(int $parentId): static
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function getChildId(): ?int
    {
        return $this->childId;
    }

    public function setChildId(int $childId): static
    {
        $this->childId = $childId;

        return $this;
    }

    public function save() : int
    {
        $resultArray = [];

        $fields = '(parent_id, child_id)';
        $params = '(:parent_id, :child_id)';

        $statement = DB::$pdo->prepare('INSERT INTO '.self::$dbTable.' '.$fields.' VALUES '.$params);

        $parentId = $this->getParentId();
        $childId = $this->getChildId();

        $statement->bindParam(':parent_id', $parentId, \PDO::PARAM_INT);
        $statement->bindParam(':child_id', $childId, \PDO::PARAM_INT);

        $statement->execute();

        Contact::deleteSameContact($parentId, $childId);

        return DB::$pdo->lastInsertId();;
    }

}
