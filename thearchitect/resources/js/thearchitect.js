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
