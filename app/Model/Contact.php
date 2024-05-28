<?php
namespace Model;

use Service\DB;

class Contact extends DB
{
    public static $dbTable = 'contact';
    
    private ?string $firstName = null;
    private ?string $lastName = null;
    private ?string $email = null;
    private ?string $street = null;
    private ?string $zip = null;
    private ?int $cityId;
    private ?int $collectionId;
    private ?string $tags = null;
    
    // static array of tag-to-contact association list
    public static $tagToContactData = []; 

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function setZip(string $zip): static
    {
        $this->zip = $zip;

        return $this;
    }

    public function getCityId(): ?int
    {
        return $this->cityId;
    }

    public function setCityId(int $cityId): static
    {
        $this->cityId = $cityId;

        return $this;
    }

    public function getCollectionId(): ?int
    {
        return $this->collectionId;
    }

    public function setCollectionId(int $collectionId): static
    {
        $this->collectionId = $collectionId;

        return $this;
    }

    public function getTags(): ?string
    {
        return $this->tags;
    }

    public function setTags(string $tags): static
    {
        $this->tags = $tags;

        return $this;
    }
    
    /* @brief:    saves a contact then saves all tags associated. */
    /* @returns:  int, the id of the new / edited entry */
    public function save(?int $id) : int
    {
        $resultArray = [];

        // Preparing the SQL statement
        if (!isset($id) || (int)$id == 0)
        {
            // If INSERT is happening
            $fields = '(first_name, last_name, email, street, zip, city_id, collection_id)';
            $params = '(:first_name, :last_name, :email, :street, :zip, :city_id, :collection_id)';

            $statement = DB::$pdo->prepare('INSERT INTO '.self::$dbTable.' '.$fields.' VALUES '.$params);
        }
        else
        {
            // If UPDATE is happening
            $fieldsAndParams = 'first_name = :first_name, 
                                last_name = :last_name,
                                email = :email, 
                                street = :street, 
                                zip = :zip, 
                                city_id = :city_id, 
                                collection_id = :collection_id';

            $statement = DB::$pdo->prepare('UPDATE '.self::$dbTable.' SET '.$fieldsAndParams. ' WHERE id = :id');

            $statement->bindParam(':id', $id, \PDO::PARAM_INT);
        }

        // Getting all the data of the object
        $firstName = $this->getFirstName();
        $lastName = $this->getLastName();
        $email = $this->getEmail();
        $street = $this->getStreet();
        $zip = $this->getZip();
        $city_id = $this->getCityId();
        $collection_id = $this->getCollectionId();

        // Binding data to prepared SQL parameters
        $statement->bindParam(':first_name', $firstName, \PDO::PARAM_STR);
        $statement->bindParam(':last_name', $lastName, \PDO::PARAM_STR);
        $statement->bindParam(':email', $email, \PDO::PARAM_STR);
        $statement->bindParam(':street', $street, \PDO::PARAM_STR);
        $statement->bindParam(':zip', $zip, \PDO::PARAM_STR);
        $statement->bindParam(':city_id', $city_id, \PDO::PARAM_INT);
        $statement->bindParam(':collection_id', $collection_id, \PDO::PARAM_INT);

        $statement->execute();

        if (!isset($id) || (int)$id == 0)
        {
            // If INSERT happened, get the last inserted id
            $id = DB::$pdo->lastInsertId();
        }

        // Tag field management
        $tags = Tag::select(['index' => 'name']);
        
        // Get tag connections that needs to be inserted, then insert them
        $tagToContactToInsert = $this->getTagToContactToInsert($id, $tags);  

        foreach ($tagToContactToInsert as $tagToContactId)
        {
            $tagToContact = new TagToContact();
            $tagToContact->setTagId($tagToContactId);
            $tagToContact->setContactId($id);

            $tagToContact->save();
        }

        // Get tag connections that needs to be deleted, then delete them (leave orphaned tags)
        $tagToContactToDelete = $this->getTagToContactToDelete($id, $tags);

        foreach ($tagToContactToDelete as $tagToContact)
        {
            TagToContact::deleteById($tagToContact);
        }

        return $id;
    }

    /* @brief:    deletes contact already existing in one of a child collection. */
    /* @returns:  int, the id of the new / edited entry */
    static public function deleteSameContact(int $parentId, int $childId): int
    {
        // get all descendant collection's contacts
        $childCollection = Collection::select(['where' => ['id' => $childId], 'options' => ['getChildCollections']]);
        // get this collection's direct-contacts
        $parentDirectContacts = Contact::select(['where' => ['collection_id' => $parentId] ]);

        $deleteIds = [];

        // check for 'same' contacts
        foreach ($parentDirectContacts as $parentContact)
        {
            foreach ($childCollection[0]['contacts'] as $childContact)
            {
                if ($parentContact['first_name'] === $childContact['first_name'] &&
                    $parentContact['last_name'] === $childContact['last_name'] &&
                    $parentContact['email'] === $childContact['email'] &&
                    $parentContact['street'] === $childContact['street'] &&
                    $parentContact['zip'] === $childContact['zip'] &&
                    $parentContact['city_id'] === $childContact['city_id'])
                {
                    $deleteIds[$parentContact['id']] = $parentContact['id'];
                }
            }
        }

        if (count($deleteIds) > 0)
        {
            // delete tags connections for all duplicate contacts
            TagToContact::delete(['where' => ['contact_id IN' => implode(',', $deleteIds)]]);

            // delete all duplicate contacts
            parent::delete(['where' => ['id IN' => implode(',', $deleteIds)]]);
        }

        return count($deleteIds);
    }

    /* @brief:    overrides DB:select, after getting all the contacts, this method is able to attach the tags associations to the contact list  */
    /* @returns:  array, the ?improved contact list */
    public static function select(?array $params = []): array
    {
        // get the contact list by requesting the DB:select method with the given parameters
        $contacts = parent::select($params);

        if (isset($params) && is_array($params) && isset($params['options']) && is_array($params['options']))
        {
            if (in_array('getTags', $params['options']))
            {
                // get all the tag-to-contact associations
                self::$tagToContactData = TagToContact::select();

                // distribute the tag associations to contacts
                foreach ($contacts as $key => $contact)
                {
                    $filterTagIds = [];

                    foreach (self::$tagToContactData as $tagToContact)
                    {
                        if ($tagToContact['contact_id'] == $contact['id'])
                        {
                            $contacts[$key]['tagToContactArray'][] = $tagToContact;

                            // array for front-end filtering
                            $filterTagIds[] = $tagToContact['tag_id'];
                        }
                    }
                    
                    // implode the front-end filtering string, ex: 1,3,5
                    $contacts[$key]['filterTagIds'] = implode(',', $filterTagIds);
                }
            }
        } 

        return $contacts;

    }

    /* @brief:    abstraction, get tags as text instead of an array of id  */
    /* @returns:  string, the comma separated tags */
    static public function getTagsAsText(?array $tagToContactArray, ?array $tags): string
    {
        $result = '';
        $textArray = [];

        foreach ($tagToContactArray as $tagToContact)
        {
            $textArray[] = $tags[$tagToContact['tag_id']]['name'];
        }

        if (is_array($textArray))
        {
            $result = implode(', ', $textArray);
        }

        return $result;
    }

    /* @brief:    abstraction, get tags that needs to be associated with a contact, processed by the save() method  */
    /* @returns:  array, of tag ids which needs to be associated  */
    public function getTagToContactToInsert(int $id, ?array $tags): array
    {
        $result = [];
        $tagArray = explode(',', $this->getTags());

        foreach ($tagArray as $tag)
        {
            $tag = trim($tag);

            if ($tag === '') continue;

            if (!isset($tags[$tag]))
            {
                $newTag = new Tag();
                $newTag->setName($tag);
                $result[] = $newTag->save();
            }
            else
            {
                $found = false;

                foreach (self::$tagToContactData as $tagToContact)
                {
                    if ($tagToContact['tag_id'] === $tags[$tag]['id'] && $tagToContact['contact_id'] === $id)
                    {
                        $found = true;
                    }
                }

                if (!$found) 
                {
                    $result[] = $tags[$tag]['id'];
                }
            }
        }

        return $result;
    }

    /* @brief:    abstraction, get tag associations that needs to be deleted, processed by the save() method  */
    /* @returns:  array, of association ids which needs to be deleted  */
    public function getTagToContactToDelete(int $id, ?array $tags): array
    {
        $result = [];
        $tagArray = explode(',', $this->getTags());

        foreach (self::$tagToContactData as $tagToContact)
        {
            if ($tagToContact['contact_id'] == $id)
            {
                $found = false;
                
                foreach ($tagArray as $tag)
                {
                    $tag = trim($tag);

                    if (isset($tags[$tag]) && $tagToContact['tag_id'] == $tags[$tag]['id'])
                    {
                        $found = true;
                    }
                }

                if (!$found) 
                {
                    $result[] = $tagToContact['id'];
                }
            }
        }

        return $result;
    }
}
