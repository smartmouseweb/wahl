<?php
namespace Model;

use Service\DB;

class Collection extends DB
{
    public static $dbTable = 'collection';
    
    private ?string $name = null;
    private array $childCollections = [];

    // static array of collection-to-collection association list
    public static $collectionToCollectionData = [];

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getChildCollections(): ?array
    {
        return $this->childCollections;
    }

    public function setChildCollections(array $childCollections): static
    {
        // we need every descenant child only once, even if it occures on multiple branches or levels
        $this->childCollections = array_filter(array_unique($childCollections));

        return $this;
    }

    /* @brief:    saves a collecion then saves all collecion-to-collection associations. */
    /* @returns:  int, the id of the new / edited entry */
    public function save(?int $id) : int
    {
        $resultArray = [];

        // Preparing the SQL statement
        if (!isset($id) || (int)$id == 0)
        {
            // If INSERT is happening
            $fields = '(name)';
            $params = '(:name)';

            $statement = DB::$pdo->prepare('INSERT INTO '.self::$dbTable.' '.$fields.' VALUES '.$params);
        }
        else
        {
            // If UPDATE is happening
            $fieldsAndParams = 'name = :name';

            $statement = DB::$pdo->prepare('UPDATE '.self::$dbTable.' SET '.$fieldsAndParams. ' WHERE id = :id');

            $statement->bindParam(':id', $id, \PDO::PARAM_INT);
        }

        // Getting all the data of the object
        $name = $this->getName();

        // Binding data to prepared SQL parameters
        $statement->bindParam(':name', $name, \PDO::PARAM_STR);

        $statement->execute();

        $childCollectionToInsert = [];
        $childCollectionToDelete = [];
        
        $childCollections = $this->getChildCollections();

        if (!isset($id) || (int)$id == 0)
        {
            // If INSERT happened, get the last inserted id
            $id = DB::$pdo->lastInsertId();

            // For a new collection, every association is new, and needs to be inserted
            $childCollectionToInsert = $childCollections;
        }
        else
        {
            // If UPDATE happened, get the collection associations that need to be inserted...
            $childCollectionToInsert = $this->getChildCollectionsToInsert($id);
            // ...and get those that need to be deleted
            $childCollectionToDelete = $this->getChildCollectionsToDelete($id);
        }

        // Create and persis the collection associations 
        foreach ($childCollectionToInsert as $childCollection)
        {
            $collectionToCollection = new CollectionToCollection();
            $collectionToCollection->setParentId($id);
            $collectionToCollection->setChildId($childCollection);

            $collectionToCollection->save();
        }

        // Delete the missing collection associations
        foreach ($childCollectionToDelete as $childCollection)
        {
            CollectionToCollection::deleteById($childCollection);
        }

        return $id;
    }

    /* @brief:    overrides DB:deleteById, in addtion it deletes all collection association for the current collection  */
    /* @returns:  bool, the result of the SQL statement execute */
    public static function deleteById(int $id): bool
    {
        CollectionToCollection::delete(['where' => ['parent_id' => $id]]);
        return parent::deleteById($id);
    }

    /* @brief:    overrides DB:select, after getting all the collection, this method is able to get all the descendant collections in a recursive manner  */
    /* @returns:  array, the ?improved contact list */
    public static function select(?array $params = []): array
    {
        // get the collection list with the given parameters
        $collections = parent::select($params);

        if (isset($params) && is_array($params) && isset($params['options']) && is_array($params['options']))
        {
            if (in_array('getChildCollections', $params['options']))
            {
                // get all the collection-to-collection associations, and save it in a static array for further usage
                self::$collectionToCollectionData = CollectionToCollection::select();

                foreach ($collections as $key => $collection)
                {
                    // find all non-direct child associations
                    $collections[$key]['childCollectionIds'] = self::getChildCollectionIds($collection['id'], []);

                    // query all contacts for all children associations
                    $collections[$key]['contacts'] = Contact::select(['where' => ['collection_id IN' => implode(',', $collections[$key]['childCollectionIds']) ], 'groupBy' => 'first_name, last_name, email, street, zip, city_id']);

                    // print_r($childCollectionIds);
                }
            }
        } 

        return $collections;

    }

    /* @brief:    get all children collections by recursively traveling down on the collection tree  */
    /* @returns:  array, the child collection ids */
    public static function getChildCollectionIds(int $parentId, array $visited): array
    {
        $result = [$parentId];

        foreach (self::$collectionToCollectionData as $collectionToCollection)
        {
            if ($collectionToCollection['parent_id'] == $parentId)
            {
                // avoid loops with 'Depth First Search' technique
                if (!in_array($collectionToCollection['child_id'], $visited))
                {
                    // we need a child collection id only once (unique) 
                    $result = array_unique(array_merge($result, self::getChildCollectionIds($collectionToCollection['child_id'], $result)));
                }
            }
        }

        return $result;
    }

    /* @brief:    get direct-child collections for a given collection id  */
    /* @returns:  array, the child collection ids */
    public static function getDirectChildCollectionIds(int $parentId): array
    {
        $result = [];

        foreach (self::$collectionToCollectionData as $collectionToCollection)
        {
            if ($collectionToCollection['parent_id'] == $parentId)
            {
                $result[] = $collectionToCollection['child_id'];
            }
        }

        return $result;
    }

    /* @brief:    abstraction, get collection that needs to be associated with a given collection, processed by the save() method  */
    /* @returns:  array, of collection ids which needs to be associated  */
    public function getChildCollectionsToInsert(int $parentId): array
    {
        $childCollectionToInsert = [];
        foreach ($this->getChildCollections() as $childCollectionId)
        {
            $found = false;

            foreach (self::$collectionToCollectionData as $collectionToCollection)
            {
                if ($collectionToCollection['parent_id'] == $parentId)
                {
                    if ($collectionToCollection['child_id'] == $childCollectionId)
                    {
                        $found = true;
                    }
                }
            }

            if (!$found)
            {
                $childCollectionToInsert[] = $childCollectionId;
            }
        }

        return $childCollectionToInsert ?? [];
    }

    /* @brief:    abstraction, get collection associations that needs to be deleted, processed by the save() method  */
    /* @returns:  array, of association ids which needs to be deleted  */
    public function getChildCollectionsToDelete(int $parentId): array
    {
        foreach (self::$collectionToCollectionData as $collectionToCollection)
        {
            if ($collectionToCollection['parent_id'] == $parentId)
            {
                $found = false;
                foreach ($this->getChildCollections() as $childCollectionId)
                {
                    if ($collectionToCollection['child_id'] == $childCollectionId)
                    {
                        $found = true;
                    }
                }

                if (!$found)
                {
                    $childCollectionToDelete[] = $collectionToCollection['id'];
                }
            }
        }

        return $childCollectionToDelete ?? [];
    }

}
