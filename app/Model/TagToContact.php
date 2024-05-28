<?php
namespace Model;

use Service\DB;

class TagToContact extends DB
{
    public static $dbTable = 'tag_to_contact';
    
    private int $tagId = 0;
    private int $contactId = 0;

    public function getTagId(): ?int
    {
        return $this->tagId;
    }

    public function setTagId(int $tagId): static
    {
        $this->tagId = $tagId;

        return $this;
    }

    public function getContactId(): ?int
    {
        return $this->contactId;
    }

    public function setContactId(int $contactId): static
    {
        $this->contactId = $contactId;

        return $this;
    }

    public function save() : int
    {
        $resultArray = [];

        $fields = '(tag_id, contact_id)';
        $params = '(:tag_id, :contact_id)';

        $statement = DB::$pdo->prepare('INSERT INTO '.self::$dbTable.' '.$fields.' VALUES '.$params);

        $tagId = $this->getTagId();
        $contactId = $this->getContactId();

        $statement->bindParam(':tag_id', $tagId, \PDO::PARAM_INT);
        $statement->bindParam(':contact_id', $contactId, \PDO::PARAM_INT);

        $statement->execute();

        return DB::$pdo->lastInsertId();;
    }

}
