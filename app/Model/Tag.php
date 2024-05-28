<?php
namespace Model;

use Service\DB;

class Tag extends DB
{
    public static $dbTable = 'tag';

    private ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function save() : int
    {
        $resultArray = [];

        $fields = '(name)';
        $params = '(:name)';

        $statement = DB::$pdo->prepare('INSERT INTO '.self::$dbTable.' '.$fields.' VALUES '.$params);

        $name = $this->getName();

        $statement->bindParam(':name', $name, \PDO::PARAM_STR);

        $statement->execute();

        return DB::$pdo->lastInsertId();;
    }
}
?>
