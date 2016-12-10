jQuery(function($) {
var nav    = $('.filter'),
	space  = $('.filter-line-space'),
    offset = nav.offset();
$(window).scroll(function () {
// 40
  if($(window).scrollTop() +40 > offset.top) {
    nav.addClass('filter-top');
    space.addClass('filter-space-set');
  } else {
    nav.removeClass('filter-top');
    space.removeClass('filter-space-set');
  }
});
});