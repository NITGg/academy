$( document ).ready(function() {
    $('body').animate({
        width: "100%",
      }, 1500 );
// $('#submit_teacher').click(function(event){
//     // event.preventDefault();

  

// });
$( "#first_form" ).submit(function( event ) {
    // event.preventDefault();
    $.ajax({ 
        type: "POST",
        data: $('#first_form').serialize(),
        url: "bbb2.php",
        cache:false,
        success: function (status) {
            // $("#first_form").fadeOut(3000);
            // $("p").fadeIn(3000);
        }});
  });


});