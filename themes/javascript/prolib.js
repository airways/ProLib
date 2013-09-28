var prolib = {

    bind_events: function() {
        $('a.pl_confirm').click(function(event) {
            if(!confirm($(this).attr('rel'))) {
                return false;
            }
        })
        
        $('input.pl_confirm').click(function(event) {
            if(!confirm($(this).attr('rel'))) {
                return false;
            }
        })
    }

}


$(document).ready(function() {
    prolib.bind_events();
});