<?php
namespace Model;

use Service\DB;

class City extends DB
{
    public static $dbTable = 'city';

    private ?string $name = null;

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
?>
