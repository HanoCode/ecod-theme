$("body").mousemove(function(event) {
  var rotateY = -event.pageX + $("body").width() * 0.5;
  var rotateX = event.pageY - $("body").height() * 0.5;
  $("#card").css({
    transform:
      "perspective(600px) rotateX(" +
      rotateX / 10 +
      "deg) rotateY(" +
      rotateY / 10 +
      "deg) "
  });
  var glazex = -530 + event.pageX / $("body").width() * 500;
  $("#glaze").css({ transform: " translateX(" + glazex + "px) translateY(0px)"});
 // $("#bot").css({ transform: " perspective(600px) translateZ(" + event.pageX / $("body").width()*100 + "px) "});
 //  $("#mid").css({ transform: " perspective(600px) translateZ(" + event.pageX / $("body").width()*100 + "px) "});
  
  
  
});
