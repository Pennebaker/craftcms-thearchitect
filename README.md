![The Architect](/the-architect.png?raw=true)
# The Architect for [Craft CMS](http://buildwithcraft.com/)

CraftCMS Plugin to Construct Groups, Fields, Sections, EntryTypes, Transforms, Globals, Assets, Categories, and Users & User Groups from JSON data.

## Installation
1. Move the `thearchitect` directory into your `craft/plugins` directory.
2. Go to Settings &gt; Plugins from your Craft control panel and enable the `thearchitect` plugin

Example files can be found in the `library` directory

## Exported Constructs
If you want to provide json files to be loaded throught the CP. Put the files in `craft/config/thearchitect`. If using a version prior to v1.6.0 the folder for these files is `craft/plugins/thearchitect/content`. This path is also configurable by creating a config file at `craft/config/thearchitect.php`
```php
'modelsPath' => str_replace('plugins', 'config', __dir__.'/'),
```

## Migration File
The migration file is named `_master_.json` and located inside the folder with the other json files listed above. Migration files are intended to be used within a single site. They are not intended to transfer content models between websites.

## Rollback
As of version 1.6.0, if Craft crashes with an exception, the Architect will roll back any changes that were made to the database for the operation. This should help prevent any issues that might appear from a partial import. If an exception happens please report them to on the [repo's issues](https://github.com/Pennebaker/craftcms-thearchitect/issues)

## JSON Schema
The example / syntax schemas are located on the [Repo's Wiki](https://github.com/Pennebaker/craftcms-thearchitect/wiki)

If you're using the [Atom text editor](https://atom.io/), you can download a [snippet library](https://github.com/Emkaytoo/craft-json-snippets) to help speed up your writing custom models for the plugin.

## Field Layouts using names instead of handles
If you have some field layouts that use names this functionality was dropped in version 1.0.3. Alternatively you can update your old models to use handles to fix them for newer versions.

*Special thanks to [Shannon Threadgill](https://dribbble.com/threadgillthunder) for his totally boss illustrations.*
