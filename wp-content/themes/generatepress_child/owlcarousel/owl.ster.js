const $ = jQuery;

// owl stering

$(document).ready(function(){
    $('.karuzela > .fusion-builder-row.fusion-row').addClass('owl-carousel owl-theme owl-loaded owl-drag');
   });

$(document).ready(function(){
    $(".owl-carousel").owlCarousel({
        loop:false,
        margin:10,
        responsiveClass:true,
        slideBy: 1,
        mouseDrag: true,
        autoplay: false,
        autoplayTimeout: 3200,
        autoplaySpeed: 650,
        autoplayHoverPause: true,
        responsive:{
            0:{
                items:1,
                nav:false
            },
            600:{
                items:2,
                nav:false
            },
            1000:{
                items:3,
                nav:true,
                dots:false
            }
        }
    }
    );
  });

