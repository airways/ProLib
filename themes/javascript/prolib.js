var prolib = {
    select_all_check: function(event)
    {
//        console.log($('input#pl_select_all'));

        setTimeout (function() {
            if ($('input#pl_select_all').prop('checked'))
            {
                $('#pl_select_all_entries_span').show();
            } else {
                $('#pl_select_all_entries_span').hide();
                $('#pl_all_entries_selected').hide();
                $('[name=select_all_entries]').val(0);
            
            }
        }, 100);
    },
    
    bind_events: function() {
        $('a.pl_confirm').click(function(event) {
            if(!confirm($(this).attr('rel'))) {
                return false;
            }
        })
        
        $('input.pl_confirm').click(function(event) {
            if(!confirm($(this).attr('data-prompt'))) {
                return false;
            }
        })

        
        $('th').click(prolib.select_all_check)
        $('input#pl_select_all').change(prolib.select_all_check)
        $('input.batch_id').click(prolib.select_all_check)

        $('a#pl_select_all_entries_link').click(function(event) {
            $('[name=select_all_entries]').val(1);
            $('#pl_select_all_entries_span').hide();
            $('#pl_all_entries_selected').show();
            
        })

        $('input#pl_batch_submit').click(function(event) {

            if ($('[name=select_all_entries]').val() == 1)
            {
                var count = $('#pl_select_all_entries_span').attr('data-entry-count')
            } else {
                var count = $('input.batch_id:checked').length;
            }
            
            if (count <= 0) 
            {
                alert('No entries selected!');
                event.preventDefault();
                return false;
            }
                
            switch ($('select[name=batch_command]').val())
            {
                case 'delete':  
                
                    if(count == 1) {cardinality = 'entry'} else {cardinality = 'entries'}
                    if(!confirm('Are you sure you want to delete ' + count + ' selected ' + cardinality + '?')) 
                    {
                        return false;
                    }
                    break;
                default:
                    break;
            }
        })
    }

}


$(document).ready(function() {
    prolib.bind_events();
});