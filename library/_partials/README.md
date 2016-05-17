# Partials & Modular Content Management

The partials section has to do primarily with blocks within Matrix fields. The models contained within are made to be copied and pasted into a Matrix field as block-level objects. See below for an example:

```
{
  "group": "Default",
  "name": "Matrix",
  "handle": "matrix",
  "instructions": "Use this area to build out content for this page.",
  "type": "Matrix",
  "typesettings": {
    "blockTypes":{
      "new1": // Copy and paste the entire block-level model here, including the curly braces.
    }
  }
}
```

The `new1` object shown above doesn't have braces that defines it as an object, because each partial/block-level model includes the braces that define it. This ensures that each block-level model validates when run through a linter.

Block-level models in the partials directory are not configured to drag and drop into the Generator as solo fields. They lack the proper structure for the plugin to read them properly. They are meant to be added as is to a Matrix field type.

## Examples  
Examples will be added to the [Plugin Wiki](https://github.com/Pennebaker/craftcms-generator/wiki/Block-Examples).
