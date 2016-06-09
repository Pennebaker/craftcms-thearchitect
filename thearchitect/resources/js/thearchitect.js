$(function() {
  $('#allFields').on('change', function(e) {
    if ($(this).is(':checked')) {
      $('.fields [id^="field"]:not(:disabled)').prop('checked', true);
    } else {
      $('.fields [id^="field"]:not(:disabled)').prop('checked', false);
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
    } else {
      $('.sections [id^="section"]:not(:disabled)').prop('checked', false);
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
    } else {
      $('.assetSources [id^="assetSource"]:not(:disabled)').prop('checked', false);
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
    } else {
      $('.assetTransforms [id^="assetTransform"]:not(:disabled)').prop('checked', false);
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
    } else {
      $('.globals [id^="global"]:not(:disabled)').prop('checked', false);
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
});

(function($) {
  // The Architect Loaded
})(jQuery);
