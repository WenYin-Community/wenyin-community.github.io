jQuery(function($){
      // Smooth scroll
      $(document).ready(function() {
            $('.nav-main').bind('click', 'ul li a', function(e) {
                  e.preventDefault();
                  $.scrollTo(e.target.hash, 600, {offset:-150});
            });
      });
});

window.addEventListener('DOMContentLoaded', function() {
      // Preloader
      $("body").queryLoader2({
            barColor: "#2f2f2f",
            backgroundColor: "#f7f7f7",
            percentage: true,
            barHeight: 1,
            completeAnimation: "grow",
            minimumTime: 100
      });
});