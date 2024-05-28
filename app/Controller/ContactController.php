<?php
namespace Controller;

use Model\City;
use Model\Collection;
use Model\Contact;
use Model\Tag;
use Model\TagToContact;
use Service\AbstractController;

class ContactController extends AbstractController
{
  /* @brief: a static dataset variable for storing all the data requested from the DB. */
  public static $renderParams = [];

  /* @brief:    rout for /, displays the index page with the Contact list */
  /* @returns:  string, rendered page */
  static public function index() : string
  {
    $addressBookData = self::getAddressBookData();

    self::$renderParams['addressBookData'] = $addressBookData;

    return parent::render('contact.html', self::$renderParams);
  }

  /* @brief:    rout for /create/, displays a form for creating a new contact and the Contact list */
  /* @returns:  string, rendered page */
  static public function create() : string
  {
    $addressBookData = self::getAddressBookData();

    self::$renderParams['addressBookData'] = $addressBookData;
    self::$renderParams['action'] = 'create';

    return parent::render('contact.html', self::$renderParams);
  }

  /* @brief:    rout for /edit/{id}, displays a form for editing an existing contact and the Contact list */
  /* @returns:  string, rendered page */
  static public function edit(int $id) : string
  {
    $addressBookData = self::getAddressBookData();

    if (isset($id) && isset($addressBookData['contacts'][$id])) 
    {
      // Get comma separated tags if needed
      if (isset($addressBookData['contacts'][$id]['tagToContactArray']))
      {
        $addressBookData['contacts'][$id]['tags'] = Contact::getTagsAsText($addressBookData['contacts'][$id]['tagToContactArray'], $addressBookData['tags']);
      }

      self::$renderParams['editContact'] = $addressBookData['contacts'][$id]; // ToDo: if pagination is implemented, change this to a DB select
    }
    else
    {
      self::$renderParams['messages'][] = ['msg' => 'Contact not found', 'type' =>'danger'];
    }

    self::$renderParams['addressBookData'] = $addressBookData;
    self::$renderParams['action'] = 'edit';

    return parent::render('contact.html', self::$renderParams);
  }

  /* @brief:    rout for /save/, processes and saves the contact form and displays the updated Contact list */
  /* @returns:  string, rendered page */
  static public function save() : string
  {
    $addressBookData = self::getAddressBookData();

    if (isset($_POST['first_name']) && $_POST['first_name'] !== '' &&
        isset($_POST['last_name']) && $_POST['last_name'] !== '' &&
        isset($_POST['email']) && $_POST['email'] !== '' && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)  &&
        isset($_POST['street']) && $_POST['street'] !== '' &&
        isset($_POST['zip']) && $_POST['zip'] !== '' &&
        isset($_POST['city_id']) && $_POST['city_id'] !== '' &&
        isset($_POST['collection_id']) && $_POST['collection_id'] !== '')
    {
        // Create a new Contact object, set all the data with setters, then save.
        $contact = new Contact();
        $contact->setFirstName($_POST['first_name']);
        $contact->setLastName($_POST['last_name']);
        $contact->setEmail($_POST['email']);
        $contact->setStreet($_POST['street']);
        $contact->setZip($_POST['zip']);
        $contact->setCityId($_POST['city_id']);
        $contact->setCollectionId($_POST['collection_id']);
        $contact->setTags($_POST['tags']);

        $savedId = $contact->save(isset($_POST['id']) && isset($addressBookData['contacts'][$_POST['id']]) ? $_POST['id'] : 0);

        if (isset($savedId) && $savedId !== 0)
        {
          // Update the contact list. 
          // ToDo: optimize by adding the new/edited entry to the existing array instead of re-querying the dataset
          $addressBookData = self::getAddressBookData(); 

          self::$renderParams['messages'][] = ['msg' => 'The contact was saved successfully!', 'type' =>'success'];
        }
    }
    else
    {
      self::$renderParams['messages'][] = ['msg' => 'Some values are missing or invalid, please complete accordingly.', 'type' =>'danger'];
    }
    
    self::$renderParams['addressBookData'] = $addressBookData;

    return parent::render('contact.html', self::$renderParams);
  }

  /* @brief:    rout for /delete/, deletes a contact and displays the updated Contact list */
  /* @returns:  string, rendered page */
  static public function delete(int $id) : string
  {
    $addressBookData = self::getAddressBookData();

    if (isset($id) && isset($addressBookData['contacts'][$id])) 
    {
      // Delete the Tags associations (leave the orphaned tags)
      TagToContact::delete(['where' => ['contact_id' => $id]]);

      Contact::deleteById($id);

      // Update the dataset
      unset($addressBookData['contacts'][$id]);

      self::$renderParams['messages'][] = ['msg' => 'The contact was deleted successfully!', 'type' =>'success'];
    }
    else
    {
      self::$renderParams['messages'][] = ['msg' => 'Contact not found', 'type' =>'danger'];
    }
    
    self::$renderParams['addressBookData'] = $addressBookData;

    return parent::render('contact.html', self::$renderParams);
  }

  /* @brief:    gets all the Contact, Cities, Collections, Tags data in one place. */
  /* @returns:  an array with all the datas for further processing and displaying in the template */
  static public function getAddressBookData() : array
  {
    $contacts = Contact::select(['index' => true, 'options' => ['getTags']]);
    $cities = City::select(['index' => true]);
    $collections = Collection::select(['index' => true]);
    $tags = Tag::select(['index' => true]);
    $tagsToDisplay = TagToContact::select(['groupBy' => 'tag_id']);

    return ['contacts' => $contacts, 'cities' => $cities, 'collections' => $collections, 'tags' => $tags, 'tagsToDisplay' => $tagsToDisplay];
  }

  /* @brief:    creates and XML object with all the contacts available */
  /* @returns:  void, but it outputs the contact list in XML format - prompted as attachment */
  static public function exportXml() : void
  {
    $addressBookData = self::getAddressBookData();

    $xml = new \SimpleXMLElement('<?xml version="1.0"?><data></data>');
    foreach ($addressBookData['contacts'] as $contact)
    {
      $contactXml = $xml->addChild('contact');
      foreach ($contact as $field => $value)
      {
        $contactXml->addChild($field, htmlspecialchars($field == 'city_id' ? $addressBookData['cities'][$value]['name'] : $value));
      }
    }

    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="addressBook.xml"');
    print $xml->asXML();
    return;
  }

  /* @brief:    creates a JSON object with all the contacts available */
  /* @returns:  void, but it outputs the contact list in JSON format - prompted as attachment */
  static public function exportJson() : void
  {
    $addressBookData = self::getAddressBookData();

    foreach ($addressBookData['contacts'] as $key => $contact)
    {
      $addressBookData['contacts'][$key]['city'] = $addressBookData['cities'][$contact['city_id']]['name'];
    }

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="addressBook.json"');
    print json_encode($addressBookData['contacts']);
    return;
  }

  
}
?>
