<?php
namespace Craft;

class TheArchitectVariable
{
    /**
     * Returns a field layout by its ID.
     *
     * @param int $layoutId
     *
     * @return FieldLayoutModel|null
     */
    public function getNeoBlockTypesByFieldId($layoutId)
    {
        return craft()->neo->getBlockTypesByFieldId($layoutId);
    }

    /**
     * Returns an array of all the users.
     *
     * @return array[UserModel]
     */
   public function getAllUsers()
   {
       return craft()->theArchitect->getAllUsers();
   }

   /**
    * Returns an array of all the users.
    *
    * @return array[UserModel]
    */
  public function getAllTagGroups()
  {
      return craft()->tags->getAllTagGroups();
  }

   /**
    * Returns a entry type by its ID.
    *
    * @param int $entryTypeId
    *
    * @return EntryTypeModel|null
    */
   public function getEntryTypeById($entryTypeId)
   {
       return craft()->sections->getEntryTypeById($entryTypeId);
   }

   /**
    * Returns a asset source by its ID.
    *
    * @param int $sourceId
    *
    * @return AssetSourceModel|null
    */
   public function getSourceById($sourceId)
   {
       return craft()->assetSources->getSourceById($sourceId);
   }

   /**
    * Returns a asset transform by its ID.
    *
    * @param int $transformId
    *
    * @return AssetTransformModel|null
    */
   public function getTransformById($transformId)
   {
       foreach (craft()->assetTransforms->getAllTransforms() as $transform) {
           if ($transform->id == $transformId) {
               return $transform;
           }
       }
   }

   /**
    * Returns a category group by its ID.
    *
    * @param int $categoryId
    *
    * @return CategoryGroupModel|null
    */
   public function getCategoryGroupById($categoryId)
   {
       foreach (craft()->categories->getAllGroups() as $category) {
           if ($category->id == $categoryId) {
               return $category;
           }
       }
   }

   /**
    * Returns a user by its ID.
    *
    * @param int $userId
    *
    * @return UserModel|null
    */
   public function getUserById($userId)
   {
       return craft()->users->getUserById($userId);
   }

   /**
    * Returns a integer of the license edition
    *
    * @return Integer
    */
   public function getEdition()
   {
       return craft()->getEdition();
   }
}
