function showFullText(name) {
    $("#short_"+name).hide();
    $("#"+name).removeClass("offscreen");
}

function hideFullText(name) {
    $("#short_"+name).show();
    $("#"+name).addClass("offscreen");
}

function toggleMenu(elemId) {
    var elem = $("#"+elemId);
    if (elem.hasClass("offscreen")) {
        elem.removeClass("offscreen");
    } else {
        elem.addClass("offscreen");
    }
}

$(document).ready(function(){
    $(".konspekt_mainlink").click(function(event) {
        event.stopPropagation();
        $(this).parent().find("div.konspekt_sublinks").toggle();
    });


    $('.imagespop').magnificPopup({
      delegate: 'a', // child items selector, by clicking on it popup will open
      type: 'image',
      gallery:{enabled:true}
    });

    $('a.summURLs').click(function(){
    	$(this).parent().find("ul.urlsmenu").toggle();
    });


})

