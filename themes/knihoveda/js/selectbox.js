/*
 The Discover module javascript
 **/
$(document).ready(function() {
    /*
     * Auto executing function
     * If the first tab is active then use an
     * XHT call to load the content of the right column
     **/
    var onLoad = function () {

	   $('#searchForm input[type="submit"]').on('click', function (event) {
            var form = $(this).parents('form');
            var radio = form.find('input[name="service"]:checked');
	        if(radio.attr('id')=='service-EDS') {
                    form.attr('action', '/EDS/Search');
            }
            $(form).submit();
            return true;
        });

       $("#service-EDS").change(function() {
            // console.log('kliknul');

            var form = $(this).parents('form');
            var radio = form.find('input[name="service"]:checked');
            if(radio.attr('id')=='service-EDS') {
                form.attr('action', '/EDS/Search');
            } else {
                form.attr('action', '/Search/Results');
            }

            // var selectbox = document.getElementById("searchForm_type");
            // var searchVal = selectbox.options[selectbox.selectedIndex].value;
            // var choice = $("#searchForm_type");
            // var types = knavTypes;
            // choice.empty();
            // var form = $(this).parents('form');
            // var box = form.find('input[name="service"]:checked');

            // if(box.attr('id')=='service-articles') {
            //     types = ebscoTypes;
            // }

            // $.each(types, function(index, value) {
            //     if(searchVal == index){
            //     choice.append("<option value=" + index + " selected='selected' " + " > " + value + "</option>");
            //   } else {
            //     choice.append("<option value=" + index + "> " + value + "</option>");
            //   }
            // });


        });


    }();
});