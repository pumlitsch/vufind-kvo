$(document).ready(function() {
    checkHistorical();
});

function checkHistorical() {
    var id = $.map($('.ajax_historical'), function(i) {
        // console.log('pis');
        return $(i).attr('id').substr('status'.length);
    });
    if (id.length) {
        // console.log('pisu');
        $(".ajax_historical").show();
        $.ajax({
            dataType: 'json',
            url: VuFind.path + '/AJAX/JSON?method=getHistoricalStatus',
            data: {id:id},
            success: function(response) {
                if(response.status == 'OK') {
                    $('#status' + id).empty().append(response.data.message);
                } else {
                    // display the error message on each of the ajax status place holder
                    $(".ajax_historical").empty().append(response.data);
                }
                $(".ajax_historical").removeClass('ajax_historical');
            }
        });
    }
}
