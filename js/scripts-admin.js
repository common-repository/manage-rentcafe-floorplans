// Set up App object and jQuery
var App = App || {},
  $ = $ || jQuery;

App.disableFields = function() {
  $('input[type=text]').prop('disabled', true);
  $('input[type=checkbox]').prop('checked', false);
  $('select').prop('disabled', true);
}

App.toggleCustomApplyNow = function() {
  $('.custom-apply-now input[type=checkbox]').change(function() {
    if (this.checked) { 
      $('.apply-now-link input[type=text]').prop('disabled', false);
    } else { 
      $('.apply-now-link input[type=text]').prop('disabled', true);
    }
  });
}

$(window).on('load', function() {
  App.disableFields();
  App.toggleCustomApplyNow();
});