jQuery(function ($) {
  var $list = $("#nh-notes-sortable");
  if (!$list.length) return;

  $list.sortable({
    items: ".nh-note-item",
    axis: "y",
    placeholder: "nh-note-placeholder",
  });
});
