function gpeSubmit(){

    var amount = $("#gpeAmount").val();
    $.ajax({
        dataType: 'json',
        cache: false,
        async: false,
	    method: 'POST',
        data: {amount:amount},
        url: VuFind.path + '/AJAX/JSON?method=getGpeData',
        success: function(response) {
            if(response.status == 'OK') {
                $("#gpeTime").val(response.data.TIME);
		        $("#gpeDigest").val(response.data.DIGEST);
            }

        }
    });

    return true;
};