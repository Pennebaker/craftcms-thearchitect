$(function() {
  $('#allFields').on('change', function(e) {
    if ($(this).is(':checked')) {
      $('.fields [id^="field"]:not(:disabled)').prop('checked', true);
      $('.fields [id^="field"]:not(:disabled)').change();
    } else {
      $('.fields [id^="field"]:not(:disabled)').prop('checked', false);
      $('.fields [id^="field"]:not(:disabled)').change();
    }
  });
  $('.fields [id^="field"]:not(:disabled)').on('change', function(e) {
    if ($(this).is(':checked')) {
      if ($('.fields [id^="field"]:checked:not(:disabled)').length == $('.fields [id^="field"]:not(:disabled)').length) {
        $('#allFields').prop('checked', true);
      }
    } else {
      $('#allFields').prop('checked', false);
    }
  });

  $('#allSections').on('change', function(e) {
    if ($(this).is(':checked')) {
      $('.sections [id^="section"]:not(:disabled)').prop('checked', true);
      $('.sections [id^="section"]:not(:disabled)').change();
    } else {
      $('.sections [id^="section"]:not(:disabled)').prop('checked', false);
      $('.sections [id^="section"]:not(:disabled)').change();
    }
  });
  $('.sections [id^="section"]:not(:disabled)').on('change', function(e) {
    if ($(this).is(':checked')) {
      if ($('.sections [id^="section"]:checked:not(:disabled)').length == $('.sections [id^="section"]:not(:disabled)').length) {
        $('#allSections').prop('checked', true);
      }
    } else {
      $('#allSections').prop('checked', false);
    }
  });

  $('#allAssetSources').on('change', function(e) {
    if ($(this).is(':checked')) {
      $('.assetSources [id^="assetSource"]:not(:disabled)').prop('checked', true);
      $('.assetSources [id^="assetSource"]:not(:disabled)').change();
    } else {
      $('.assetSources [id^="assetSource"]:not(:disabled)').prop('checked', false);
      $('.assetSources [id^="assetSource"]:not(:disabled)').change();
    }
  });
  $('.assetSources [id^="assetSource"]:not(:disabled)').on('change', function(e) {
    if ($(this).is(':checked')) {
      if ($('.assetSources [id^="assetSource"]:checked:not(:disabled)').length == $('.assetSources [id^="assetSource"]:not(:disabled)').length) {
        $('#allAssetSources').prop('checked', true);
      }
    } else {
      $('#allAssetSources').prop('checked', false);
    }
  });

  $('#allAssetTransforms').on('change', function(e) {
    if ($(this).is(':checked')) {
      $('.assetTransforms [id^="assetTransform"]:not(:disabled)').prop('checked', true);
      $('.assetTransforms [id^="assetTransform"]:not(:disabled)').change();
    } else {
      $('.assetTransforms [id^="assetTransform"]:not(:disabled)').prop('checked', false);
      $('.assetTransforms [id^="assetTransform"]:not(:disabled)').change();
    }
  });
  $('.assetTransforms [id^="assetTransform"]:not(:disabled)').on('change', function(e) {
    if ($(this).is(':checked')) {
      if ($('.assetTransforms [id^="assetTransform"]:checked:not(:disabled)').length == $('.assetTransforms [id^="assetTransform"]:not(:disabled)').length) {
        $('#allAssetTransforms').prop('checked', true);
      }
    } else {
      $('#allAssetTransforms').prop('checked', false);
    }
  });

  $('#allGlobals').on('change', function(e) {
    if ($(this).is(':checked')) {
      $('.globals [id^="global"]:not(:disabled)').prop('checked', true);
      $('.globals [id^="global"]:not(:disabled)').change();
    } else {
      $('.globals [id^="global"]:not(:disabled)').prop('checked', false);
      $('.globals [id^="global"]:not(:disabled)').change();
    }
  });
  $('.globals [id^="global"]:not(:disabled)').on('change', function(e) {
    if ($(this).is(':checked')) {
      if ($('.globals [id^="global"]:checked:not(:disabled)').length == $('.globals [id^="global"]:not(:disabled)').length) {
        $('#allGlobals').prop('checked', true);
      }
    } else {
      $('#allGlobals').prop('checked', false);
    }
  });

  $('#allCategories').on('change', function(e) {
    if ($(this).is(':checked')) {
      $('.categories [id^="category"]:not(:disabled)').prop('checked', true);
      $('.categories [id^="category"]:not(:disabled)').change();
    } else {
      $('.categories [id^="category"]:not(:disabled)').prop('checked', false);
      $('.categories [id^="category"]:not(:disabled)').change();
    }
  });
  $('.categories [id^="category"]:not(:disabled)').on('change', function(e) {
    if ($(this).is(':checked')) {
      if ($('.categories [id^="category"]:checked:not(:disabled)').length == $('.categories [id^="category"]:not(:disabled)').length) {
        $('#allCategories').prop('checked', true);
      }
    } else {
      $('#allCategories').prop('checked', false);
    }
  });

  $('#allRoutes').on('change', function(e) {
    if ($(this).is(':checked')) {
      $('.routes [id^="route"]:not(:disabled)').prop('checked', true);
      $('.routes [id^="route"]:not(:disabled)').change();
    } else {
      $('.routes [id^="route"]:not(:disabled)').prop('checked', false);
      $('.routes [id^="route"]:not(:disabled)').change();
    }
  });
  $('.routes [id^="route"]:not(:disabled)').on('change', function(e) {
    if ($(this).is(':checked')) {
      if ($('.routes [id^="route"]:checked:not(:disabled)').length == $('.routes [id^="route"]:not(:disabled)').length) {
        $('#allRoutes').prop('checked', true);
      }
    } else {
      $('#allRoutes').prop('checked', false);
    }
  });

  $('#allTags').on('change', function(e) {
    if ($(this).is(':checked')) {
      $('.tags [id^="tag"]:not(:disabled)').prop('checked', true);
      $('.tags [id^="tag"]:not(:disabled)').change();
    } else {
      $('.tags [id^="tag"]:not(:disabled)').prop('checked', false);
      $('.tags [id^="tag"]:not(:disabled)').change();
    }
  });
  $('.tags [id^="tag"]:not(:disabled)').on('change', function(e) {
    if ($(this).is(':checked')) {
      if ($('.tags [id^="tag"]:checked:not(:disabled)').length == $('.tags [id^="tag"]:not(:disabled)').length) {
        $('#allTags').prop('checked', true);
      }
    } else {
      $('#allTags').prop('checked', false);
    }
  });

  $('#allUsers').on('change', function(e) {
    if ($(this).is(':checked')) {
      $('.users [id^="user"]:not(:disabled)').prop('checked', true);
      $('.users [id^="user"]:not(:disabled)').change();
    } else {
      $('.users [id^="user"]:not(:disabled)').prop('checked', false);
      $('.users [id^="user"]:not(:disabled)').change();
    }
  });
  $('.users [id^="user"]:not(:disabled)').on('change', function(e) {
    if ($(this).is(':checked')) {
      if ($('.users [id^="user"]:checked:not(:disabled)').length == $('.users [id^="user"]:not(:disabled)').length) {
        $('#allUsers').prop('checked', true);
      }
    } else {
      $('#allUsers').prop('checked', false);
    }
  });

  $('#allGroups').on('change', function(e) {
    if ($(this).is(':checked')) {
      $('.groups [id^="group"]:not(:disabled)').prop('checked', true);
      $('.groups [id^="group"]:not(:disabled)').change();
    } else {
      $('.groups [id^="group"]:not(:disabled)').prop('checked', false);
      $('.groups [id^="group"]:not(:disabled)').change();
    }
  });
  $('.groups [id^="group"]:not(:disabled)').on('change', function(e) {
    if ($(this).is(':checked')) {
      if ($('.groups [id^="group"]:checked:not(:disabled)').length == $('.groups [id^="group"]:not(:disabled)').length) {
        $('#allGroups').prop('checked', true);
      }
    } else {
      $('#allGroups').prop('checked', false);
    }
  });

  $('[data-fields] [type="checkbox"]').on('change', function(e) {
    var parentRow = $(this).closest('[data-fields]');
    if ($(this).prop('checked')) {
      var utilizedFields = parentRow.data('fields').trim().split(' ');
      utilizedFields.forEach(function(id) {
        $('.fields [data-id="' + id + '"] [type="checkbox"]').prop('checked', true);
        $('.fields [data-id="' + id + '"] [type="checkbox"]').change();
      });
    }
  });

  $('[data-groups] [type="checkbox"]').on('change', function(e) {
    var parentRow = $(this).closest('[data-groups]');
    if ($(this).prop('checked')) {
      var utilizedFields = parentRow.data('groups').trim().split(' ');
      utilizedFields.forEach(function(id) {
        $('.groups [data-id="' + id + '"] [type="checkbox"]').prop('checked', true);
        $('.groups [data-id="' + id + '"] [type="checkbox"]').change();
      });
    }
  });

  $('.field[data-id] [type="checkbox"]').on('change', function(e) {
    var parentRow = $(this).closest('[data-id]');
    var id = parentRow.data('id');
    if (!$(this).prop('checked')) {
      $('[data-fields*="' + id + '"] [type="checkbox"]').prop('checked', false);
      $('[data-fields*="' + id + '"] [type="checkbox"]').change();
    }
  });

  $('#similarFields tbody tr').each(function() {
    var leftEle = $(this).find('td:first-child > pre');
    var rightEle = $(this).find('td:last-child > pre');

    var leftStr = leftEle.html();
    var rightStr = rightEle.html();

    var diff = JsDiff.diffLines(leftStr, rightStr);

    diff.forEach(function(_diff) {
      if (_diff.removed) {
        leftStr = leftStr.replace(_diff.value, '<span class="highlight">' + _diff.value + '</span>');
      }
      if (_diff.added) {
        rightStr = rightStr.replace(_diff.value, '<span class="highlight">' + _diff.value + '</span>');
      }
    });

    leftEle.html(leftStr);
    rightEle.html(rightStr);
  });
});

(function($) {
  // The Architect Loaded
})(jQuery);
