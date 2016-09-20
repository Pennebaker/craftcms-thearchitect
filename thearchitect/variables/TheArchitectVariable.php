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
}
