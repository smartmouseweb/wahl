<?php
namespace Controller;

use Model\City;
use Model\Collection;
use Model\Contact;
use Service\AbstractController;

class CollectionController extends AbstractController
{
  /* @brief: a static dataset variable for storing all the data requested from the DB. */
  public static $renderParams = [];

  /* @brief:    rout for /groups/, displays the index page with the Collection list */
  /* @returns:  string, rendered page */
  static public function index() : string
  {
    $collectionData = self::getCollectionData();
    
    self::$renderParams['collectionData'] = $collectionData;

    return parent::render('collection.html', self::$renderParams);
  }

  /* @brief:    rout for /groups/create/, displays a form for creating a new contact and the Collection list */
  /* @returns:  string, rendered page */
  static public function create() : string
  {
    $collectionData = self::getCollectionData();

    self::$renderParams['collectionData'] = $collectionData;
    self::$renderParams['action'] = 'create';
    
    return parent::render('collection.html', self::$renderParams);
  }

  /* @brief:    rout for /groups/edit/{id}, displays a form for editing an existing contact and the Collection list */
  /* @returns:  string, rendered page */
  static public function edit(int $id) : string
  {
    $collectionData = self::getCollectionData();

    if (isset($id) && isset($collectionData['collections'][$id])) 
    {
      // Get direct-child collections only for the selected collection
      $collectionData['collections'][$id]['directChildCollectionIds'] = Collection::getDirectChildCollectionIds($id);

      self::$renderParams['editCollection'] = $collectionData['collections'][$id]; // ToDo: if pagination is implemented, change this to a DB select
    }
    else
    {
      self::$renderParams['messages'][] = ['msg' => 'Contact not found', 'type' =>'danger'];
    }

    self::$renderParams['collectionData'] = $collectionData;
    self::$renderParams['action'] = 'edit';

    return parent::render('collection.html', self::$renderParams);
  }

  /* @brief:    rout for /groups/save/, processes and saves the collection form and displays the updated Collection list */
  /* @returns:  string, rendered page */
  static public function save() : string
  {
    $collectionData = self::getCollectionData();

    if (isset($_POST['name']) && $_POST['name'] !== '')
    {
        // Create a new Collection object, set all the data with setters, then save.
        $collection = new Collection();
        $collection->setName($_POST['name']);
        $collection->setChildCollections($_POST['childCollections']);

        $savedId = $collection->save(isset($_POST['id']) && isset($collectionData['collections'][$_POST['id']]) ? $_POST['id'] : 0);

        if (isset($savedId) && $savedId !== 0)
        {
          // Update the collection list. 
          // ToDo: optimize by adding the new/edited entry to the existing array instead of re-querying the dataset
          $collectionData = self::getCollectionData();

          self::$renderParams['messages'][] = ['msg' => 'The collection was saved successfully!', 'type' =>'success'];
        }
    }
    else
    {
      self::$renderParams['messages'][] = ['msg' => 'Some values are missing or invalid, please complete accordingly.', 'type' =>'danger'];
    }
    
    self::$renderParams['collectionData'] = $collectionData;

    return parent::render('collection.html', self::$renderParams);
  }

  /* @brief:    rout for /groups/delete/, deletes a collection and displays the updated Collection list */
  /* @returns:  string, rendered page */
  static public function delete(int $id) : string
  {
    $collectionData = self::getCollectionData();

    if (isset($id) && isset($collectionData['collections'][$id])) 
    {
      Collection::deleteById($id);
      unset($collectionData['collections'][$id]);
      self::$renderParams['messages'][] = ['msg' => 'The contact was deleted successfully!', 'type' =>'success'];
    }
    else
    {
      self::$renderParams['messages'][] = ['msg' => 'Contact not found', 'type' =>'danger'];
    }
    
    self::$renderParams['collectionData'] = $collectionData;

    return parent::render('collection.html', self::$renderParams);
  }

  /* @brief:    gets all the Collections and Cities in one place. */
  /* @returns:  an array with all the datas for further processing and displaying in the template */
  static public function getCollectionData() : array
  {
    $collections = Collection::select(['index' => true, 'options' => ['getChildCollections']]);
    $cities = City::select(['index' => true]);

    return ['collections' => $collections, 'cities' => $cities];
  }
}
?>
